<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;
use Wangjian\Queue\Job\Commander;

class Worker
{
    /**
     * status constants
     * @const int
     */
    const STATUS_READY = 0;
    const STATUS_RUNNING = 1;
    const STATUS_PAUSED = 2;
    const STATUS_STOPPED = 3;

    protected $workers = 4;

    protected $sleep = 5;

    protected $maxJobs = 1000;

    protected $queueInstance;

    protected $queues = ['default'];

    protected $commander;

    private $_status = self::STATUS_READY;

    private $_workers = 0;

    private $_workerPids = [];

    public function __construct(QueueInterface $queue, Commander $commander = null)
    {
        $this->queueInstance = $queue;

        if(is_null($commander)) {
            $commander = new Commander($queue);
        }
        $this->commander = $commander;
    }

    public function run()
    {
        $this->_status = self::STATUS_RUNNING;

        $this->forkWorkers();

        $this->installMasterSignals();

        while (true) {
            if($this->_status == self::STATUS_STOPPED) {
                exit;
            }

            pcntl_signal_dispatch();
        }
    }

    public function setQueues($queues)
    {
        $this->queues = $queues;

        return $this;
    }

    public function setWorkers($workers)
    {
        $this->workers = $workers;

        return $this;
    }

    public function setSleep($sleep)
    {
        $this->sleep = $sleep;

        return $this;
    }

    public function setMaxJobs($maxJobs)
    {
        $this->maxJobs = $maxJobs;

        return $this;
    }

    public  function setCommander(Commander $commander)
    {
        $this->commander = $commander;

        return $this;
    }

    protected function doWork()
    {
        $processedJobs = 0;

        while ($processedJobs < $this->maxJobs && $this->_status == self::STATUS_RUNNING) {
            $job = null;
            foreach ($this->queues as $queue) {
                $job = $this->queueInstance->pop($queue);
                if ($job instanceof AbstractJob) {
                    break;
                }
            }

            if ($job instanceof AbstractJob) {
                $this->commander->handle($job);
            } else {
                sleep($this->sleep);
            }

            pcntl_signal_dispatch();
        }
    }

    protected function installMasterSignals()
    {
        pcntl_signal(SIGTERM, array($this, 'stop'));
        pcntl_signal(SIGINT, array($this, 'stop'));
        pcntl_signal(SIGQUIT, array($this, 'stop'));
        pcntl_signal(SIGUSR1, array($this, 'pause'));
        pcntl_signal(SIGUSR2, array($this, 'unpause'));
        pcntl_signal(SIGCHLD, array($this, 'handleWorkerExit'));
    }

    protected function installWorkerSignals()
    {
        pcntl_signal(SIGTERM, array($this, 'stopWorker'));
        pcntl_signal(SIGINT, array($this, 'stopWorker'));
        pcntl_signal(SIGQUIT, array($this, 'stopWorker'));
    }

    protected function stop()
    {
        $this->stopAllWorkers();

        $this->_status = self::STATUS_STOPPED;
    }

    protected function pause()
    {
        $this->stopAllWorkers();

        $this->_status = self::STATUS_PAUSED;
    }

    protected function unpause()
    {
        $this->forkWorkers();

        $this->_status = self::STATUS_RUNNING;
    }

    protected function stopAllWorkers()
    {
        // @TODO: 不要用SIGKILL杀死子进程，选用一个特殊的专用信号
        exec(
            sprintf("ps awx -o '%p %P'|grep -w PID| awk '{ print $1  }'|xargs kill -9", posix_getpid()),
            $output,
            $returnVar
        );

        return $returnVar == 0;
    }

    protected function stopWorker()
    {
        $this->_status = self::STATUS_STOPPED;
    }

    protected function handleWorkerExit()
    {
        $pid = pcntl_wait($status);
        foreach ($this->_workerPids as $key => $value) {
            if ($value == $pid) {
                unset($this->_workerPids[$key]);
                $this->_workers--;
            }
        }

        //if the worker process is not closed by the master, restart it
        if ($status != SIGTERM) {
            $this->forkWorkers();
        }
    }

    protected function forkWorkers()
    {
        while ($this->_workers < $this->workers) {
            $pid = pcntl_fork();
            if ($pid < 0) {
                trigger_error('pcntl_fork() failed', E_USER_NOTICE);
            } elseif ($pid == 0) {
                $this->installWorkerSignals();

                $this->doWork();
                exit;
            } else {
                $this->_workers++;
                $this->_workerPids[] = $pid;
            }
        }
    }
}
