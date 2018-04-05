<?php
namespace Wangjian\Queue\Job;

abstract class TimedJob extends AbstractJob
{
    /**
     * the run timestamp of the timed job
     * @var int
     */
    protected $runAt;

    /**
     * TimedJob constructor
     * @param int|null $runAt
     */
    public function __construct($runAt = null)
    {
        parent::__construct();

        $this->runAt = is_null($runAt) ? time() : $runAt;
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
}