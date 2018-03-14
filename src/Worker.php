<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;
use Wangjian\Queue\Job\Commander;

class Worker
{
    protected $workers = 4;

    protected $sleep = 5;

    protected $maxJobs = 1000;

    protected $queueInstance;

    protected $queues = ['default'];

    private $_workers = 0;

    private $_workerPids = [];

    public function __construct(QueueInterface $queue)
    {
        $this->queueInstance = $queue;
    }

    public function run()
    {
        if (function_exists('pcntl_fork')) {
            $this->registerSignalHandlers();
            $this->forkWorkers();

            while (true) {
                pcntl_signal_dispatch();
            }
        } else {
            trigger_error('pcntl extension is disabled, only use one work process', E_USER_NOTICE);

            while (true) {
                $this->doWork();
            }
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

    protected function doWork()
    {
        $processedJobs = 0;
        $commander = new Commander($this->queueInstance);

        while ($processedJobs < $this->maxJobs) {
            $job = null;
            foreach ($this->queues as $queue) {
                $job = $this->queueInstance->pop($queue);
                if ($job instanceof AbstractJob) {
                    break;
                }
            }

            if ($job instanceof AbstractJob) {
                $commander->handle($job);
            } else {
                sleep($this->sleep);
            }
        }
    }

    protected function registerSignalHandlers()
    {
        pcntl_signal(SIGINT, array($this, 'stop'));
        pcntl_signal(SIGKILL, array($this, 'stop'));
        pcntl_signal(SIGHUP, array($this, 'stop'));
        pcntl_signal(SIGCHLD, array($this, 'handleWorkerExit'));
    }

    protected function stop()
    {
        $this->stopAllWorkers();
    }

    protected function stopAllWorkers()
    {
        exec(
            sprintf("ps awx -o '%p %P'|grep -w PID| awk '{ print $1  }'|xargs kill －9", posix_getpid()),
            $output,
            $returnVar
        );
        var_dump($output);
        var_dump($returnVar);

        return $returnVar == 0;
    }

    protected function handleWorkerExit()
    {
        $pid = pcntl_wait($status);
        $this->_workers--;
        foreach ($this->_workerPids as $key => $value) {
            if ($value == $pid) {
                unset($this->_workerPids[$key]);
            }
        }

        //如果进程不是被kill掉的，那么重启子进程
        if ($status != 9) {
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
                $this->doWork();
                exit(1);
            } elseif ($pid > 0) {
                $this->_workers++;
                $this->_workerPids[] = $pid;
            }
        }
    }
}
