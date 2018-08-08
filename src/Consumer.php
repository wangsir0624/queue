<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;

class Consumer implements ConsumerInterface
{
    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * the queues worked on
     * @var array
     */
    protected $queuesWorkedOn;

    /**
     * Consumer constructor.
     * @param QueueInterface $queue
     * @param array $queuesWorkedOn
     */
    public function __construct(QueueInterface $queue, $queuesWorkedOn = ['default'])
    {
        $this->queue = $queue;
        $this->queuesWorkedOn = $queuesWorkedOn;
    }

    /**
     * set the worked on queues
     * @param array $queues
     * @return $this
     */
    public function setQueues(array $queues)
    {
        $this->queuesWorkedOn = $queues;

        return $this;
    }

    /**
     * add a worked queue
     * @param string $queue
     * @return $this
     */
    public function addQueue($queue)
    {
        if(!in_array($queue, $this->queuesWorkedOn)) {
            $this->queuesWorkedOn[] = $queue;
        }

        return $this;
    }

    /**
     * add worked queues
     * @param array $queues
     * @return $this
     */
    public function addQueues(array $queues)
    {
        foreach($queues as $queue) {
            $this->addQueue($queue);
        }

        return $this;
    }

    /**
     * @return null|AbstractJob
     */
    public function consume()
    {
        $job = null;
        foreach ($this->queuesWorkedOn as $queue) {
            $job = $this->queue->pop($queue);
            if ($job instanceof AbstractJob) {
                break;
            }
        }

        return $job instanceof AbstractJob ? $job : null;
    }
}