<?php
namespace Wangjian\Queue\Test;

use Wangjian\Queue\Job\AbstractJob;
use Wangjian\Queue\Test\Job\FailedJob;
use Wangjian\Queue\Test\Job\NamedJob;

class RedisQueueTest extends RedisQueueTestBase
{
    public function testPushAndPop()
    {
        $job = new NamedJob();
        $this->assertEquals(true, $this->queue->push($job));
        $this->assertEquals($job->getId(), $this->queue->pop($job->getQueue())->getId());
    }

    public function testRetry()
    {
        $job = new FailedJob();
        $job->setMaxTries(2);
        $job->setInterval(1);
        $this->queue->push($job);

        $newJob = $this->queue->pop($job->getQueue());
        $this->commander->handle($newJob);
        sleep(1);
        $newJob = $this->queue->pop($job->getQueue());
        $this->assertInstanceOf(AbstractJob::class, $newJob);
        $this->commander->handle($newJob);
        sleep(1);
        $newJob = $this->queue->pop($job->getQueue());
        $this->assertNull($newJob);
    }
}
