<?php
namespace Wangjian\Queue\Test\Job;

class FailedJob extends NamedJob
{
    public function run()
    {
        parent::run();

        return false;
    }
}
