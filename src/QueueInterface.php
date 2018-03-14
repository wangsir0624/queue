<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;

interface QueueInterface
{
    /**
     * push a job to the queue
     * @param AbstractJob $job
     * @return bool
     */
    public function push(AbstractJob $job);

    /**
     * get a job from the queue
     * @param string $queue
     * @return AbstractJob|null
     */
    public function pop($queue);

    /**
     * retry a job
     * @param AbstractJob $job
     * @return bool
     */
    public function retry(AbstractJob $job);
}
