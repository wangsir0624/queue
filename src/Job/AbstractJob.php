<?php
namespace Wangjian\Queue\Job;

abstract class AbstractJob
{
    /**
     * the default queue name of this job
     * @var string
     */
    protected $queue = 'default';

    /**
     * the job name
     * @var string
     */
    protected $name = '';

    /**
     * the max retry times
     * @var int
     */
    protected $maxTries = 1;

    /**
     * the retry time interval
     * @var int
     */
    protected $interval = 5;

    /**
     * the job id
     * @var string
     */
    private $_id;

    /**
     * the failed times
     * @var int
     */
    private $_failed = 0;

    /**
     * the last failed timestamp
     * @var int
     */
    private $_last_failed_time = 0;

    /**
     * AbstractJob constructor
     */
    public function __construct()
    {
        $this->_id = md5(uniqid('', true));
    }

    /**
     * run the job
     * @return mixed
     */
    abstract public function run();

    /**
     * set the queue
     * @param string $queue  the queue name
     * @return $this
     */
    public function onQueue($queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * get the queue of this job
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * get the job name
     * @return string
     */
    public function getName()
    {
        //if the job name is empty, return the class name by default
        if (empty($this->name)) {
            return get_class($this);
        }

        return $this->name;
    }

    /**
     * set the max retry times
     * @param int $maxTries
     * @return $this
     */
    public function setMaxTries($maxTries)
    {
        $this->maxTries = $maxTries;

        return $this;
    }

    /**
     * set the retry time interval
     * @param int $interval  time interval in seconds
     * @return $this
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * get the next retry timestamp
     * @return int
     */
    public function getRetryTime()
    {
        return $this->interval + $this->_last_failed_time;
    }

    /**
     * job failed
     */
    public function failed()
    {
        $this->_failed++;
        $this->_last_failed_time = time();
    }

    /**
     * whether should retry the job
     * @return bool
     */
    public function shouldRetry()
    {
        return $this->maxTries > $this->_failed;
    }
}
