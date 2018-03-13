<?php
namespace Wangjian\Queue\Job;

use Exception;
use Exception\SkipRetryException;
use Wangjian\Queue\QueueInterface;

class Commander
{
    /**
     * Queue instance
     * @var QueueInterface
     */
    protected $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function handle(AbstractJob $job)
    {
        try {
            if($job->run() === false) {
                throw new Exception('job run failed');
            }
        } catch (SkipRetryException $e) {

        } catch (Exception $e) {
            $job->failed();
            $this->queue->retryAt($job, $job->getRetryTime());
        }
    }
}
