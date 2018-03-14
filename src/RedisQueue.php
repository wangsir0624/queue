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
        return (bool)$this->client->rpush(
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
        var_dump($this->migrateRetryJobs($queue));

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

    /**
     * migrate the retry jobs of a queue
     * @param string $queue
     * @return array
     */
    protected function migrateRetryJobs($queue)
    {
        $luaScript = <<<LUA
-- Get all of the jobs with an expired "score"...
local val = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1])

-- If we have values in the array, we will remove them from the first queue
-- and add them onto the destination queue in chunks of 100, which moves
-- all of the appropriate jobs onto the destination queue very safely.
if(next(val) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, #val - 1)

    for i = 1, #val, 100 do
        redis.call('rpush', KEYS[2], unpack(val, i, math.min(i+99, #val)))
    end
end

return val
LUA;

        return $this->client->eval(
            $luaScript,
            2,
            $this->getRetryZsetNameWithPrefix($queue),
            $this->getQueueNameWithPrefix($queue),
            time()
        );
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
