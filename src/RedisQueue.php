<?php
namespace Wangjian\Queue;

use Predis\Client;
use Wangjian\Queue\Job\AbstractJob;
use Wangjian\Queue\Job\Job;

class RedisQueue implements QueueInterface
{
    /**
     * predis client
     * @var Client
     */
    protected $client;

    protected $prefix = '';

    public function __construct(Client $client, $prefix = '')
    {
        $this->client = $client;
        $this->prefix = $prefix;
    }

    public function push(AbstractJob $job)
    {
        $this->client->rpush(
            $this->getQueueNameWithPrefix($job->queue()),
            serialize($job)
        );
    }

    public function pop($queue)
    {
        $this->migrateRetryJobs($queue);

        $data = $this->client->lpop($this->getQueueNameWithPrefix($queue));

        if (!empty($data)) {
            $job = @unserialize($data);
            if ($job instanceof AbstractJob) {
                return $job;
            }
        }

        return null;
    }

    public function retryAt(AbstractJob $job, $timestamp)
    {
        if ($job->shouldRetry()) {
            return $this->client->zadd(
                $this->getRetryZsetNameWithPrefix($job->queue()),
                $timestamp,
                serialize($job)
            );
        }

        return false;
    }

    protected function migrateRetryJobs($queue)
    {
        // TODO 待完成
        $luaScript = <<<EOT

EOT;
    }

    protected function getQueueNameWithPrefix($queue)
    {
        return $this->prefix . (empty($this->prefix) ? '' : ':') . $queue;
    }

    protected function getRetryZsetNameWithPrefix($queue)
    {
        return $this->getQueueNameWithPrefix($queue) . ':retry';
    }
}
