<?php
namespace Wangjian\Queue\Job;

abstract class AbstractJob
{
    private $_id;

    protected $maxTries = 1;

    protected $interval = 5;

    private $_failed = 0;

    public function __construct()
    {
        $this->_id = md5(uniqid('', true));
    }

    abstract public function queue();

    abstract public function run();

    public function setMaxTries($maxTries)
    {
        $this->maxTries = $maxTries;

        return $this;
    }

    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    public function getRetryTime($failedTime = null)
    {
        return $this->interval + (is_null($failedTime) ? time() : $failedTime);
    }

    public function failed()
    {
        $this->_failed++;
    }

    public function shouldRetry()
    {
        return $this->maxTries > $this->_failed;
    }
}