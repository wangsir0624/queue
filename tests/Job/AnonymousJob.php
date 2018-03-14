<?php
namespace Wangjian\Queue\Test\Job;

use Wangjian\Queue\Job\AbstractJob;

class AnonymousJob extends AbstractJob
{
    protected $queue = 'test';

    public function run()
    {
    }
}
