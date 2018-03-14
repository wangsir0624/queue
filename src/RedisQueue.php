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

    /**
     * the queue prefix
     * @var string
     */
    protected $prefix = '';

    /**
     * RedisQueue constructor
     * @param Client $client
     * @param string $prefix
     */
    public function __construct(Client $client, $prefix = '')
    {
        $this->client = $client;
        $this->prefix = $prefix;
    }

    /**
     * push a job to the queue
     * @param AbstractJob $job
     * @return bool
     */
    public function push(AbstractJob $job)
    {
        return $this->client->rpush(
            $this->getQueueNameWithPrefix($job->getQueue()),
            serialize($job)
        );
    }

    /**
     * get a job from the queue
     * @param string $queue
     * @return AbstractJob|null
     */
    public function pop($queue)
    {
        //migrate the retry jobs
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

    /**
     * retry a job
     * @param AbstractJob $job
     * @return bool
     */
    public function retry(AbstractJob $job)
    {
        if ($job->shouldRetry()) {
            return $this->client->zadd(
                $this->getRetryZsetNameWithPrefix($job->getQueue()),
                $job->getRetryTime(),
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

    /**
     * get the queue name with prefix
     * @param string $queue
     * @return string
     */
    protected function getQueueNameWithPrefix($queue)
    {
        return $this->prefix . (empty($this->prefix) ? '' : ':') . $queue;
    }

    /**
     * get the retry zset name of a queue with prefix
     * @param string $queue
     * @return string
     */
    protected function getRetryZsetNameWithPrefix($queue)
    {
        return $this->getQueueNameWithPrefix($queue) . ':retry';
    }
}
