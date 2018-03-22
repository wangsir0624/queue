<?php
namespace Wangjian\Queue\Job;

use Exception;

class TestJob extends AbstractJob
{
    protected $maxTries = 3;

    protected $queue = 'test';

    protected $name = 'myjob';

    public function run()
    {
        echo 'Hello World';
        throw new Exception('');
    }
}