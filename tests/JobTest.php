<?php
namespace Wangjian\Queue\Test;

use PHPUnit\Framework\TestCase;
use Wangjian\Queue\Test\Job\AnonymousJob;
use Wangjian\Queue\Test\Job\FailedJob;
use Wangjian\Queue\Test\Job\NamedJob;

class JobTest extends TestCase
{
    public function testGetDefaultName()
    {
        $job = new AnonymousJob();
        $this->assertEquals(get_class($job), $job->getName());
    }

    public function testSetAndGetName()
    {
        $job = new NamedJob();
        $this->assertEquals('test_job', $job->getName());
        $this->assertEquals('test_job2', $job->setName('test_job2')->getName());
    }

    public function testSetAndGetQueue()
    {
        $job = new AnonymousJob();
        $this->assertEquals('test', $job->getQueue());
        $this->assertEquals('test2', $job->setQueue('test2')->getQueue());
    }

    public function testFailed()
    {
        $job = new FailedJob();
        $job->setMaxTries(2);
        $job->setInterval(10);
        $this->assertEquals(true, $job->shouldRetry());
        $job->failed();
        $this->assertEquals(true, $job->shouldRetry());
        $job->failed();
        $this->assertEquals(false, $job->shouldRetry());
        $this->assertEquals(time() + 10, $job->getRetryTime());
    }
}
