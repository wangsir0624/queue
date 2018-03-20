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
}
