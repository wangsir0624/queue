<?php
namespace Wangjian\Queue\Job;

use Exception\SkipRetryException;
use Exception;

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
     * the run timestamp of the job
     * @var int
     */
    protected $runAt = 0;

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
     * @return mixed  the queue will retry this job when returns false
     * @throws SkipRetryException  the queue will skip the retry when throws a SkipRetryException
     * @throws Exception  the queue will retry this job when throws an Exception
     */
    abstract public function run();

    /**
     * get the job id
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * set the queue
     * @param string $queue  the queue name
     * @return $this
     */
    public function setQueue($queue)
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
     * set the job name
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
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
     * get the run timestamp
     * @return int
     */
    public function getRunAt()
    {
        return $this->runAt;
    }

    /**
     * set the run timestamp
     * @param int $runAt
     * @return $this
     */
    public function setRunAt($runAt)
    {
        $this->runAt = $runAt;

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
