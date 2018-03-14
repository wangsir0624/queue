<?php
namespace Wangjian\Queue\Test\Job;

class NamedJob extends AnonymousJob
{
    protected $name = 'test_job';
}
