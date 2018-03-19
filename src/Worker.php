<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;
use Wangjian\Queue\Job\Commander;
use swoole_process;

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

    /**
     * the worker name
     * @var string
     */
    protected $name;

    /**
     * the worker numbers
     * @var int
     */
    protected $workers = 4;

    /**
     * when the queue is empty, sleep
     * @var int
     */
    protected $sleep = 5;

    /**
     * the max jobs to restart a worker
     * @var int
     */
    protected $maxJobs = 1000;

    /**
     * QueueInterface instance
     * @var QueueInterface
     */
    protected $queueInstance;

    /**
     * the queues worked on
     * @var array
     */
    protected $queues = ['default'];

    /**
     * Commander instance
     * @var Commander
     */
    protected $commander;

    /**
     * the worker status
     * @var int
     */
    private $_status = self::STATUS_READY;

    /**
     * the current workers
     * @var int
     */
    private $_workers = 0;

    /**
     * the worker process maps
     * @var array
     */
    private $_workerProcesses = [];

    /**
     * Worker constructor
     * @param string $name  the worker name
     * @param QueueInterface $queue
     * @param Commander $commander
     */
    public function __construct($name, QueueInterface $queue, Commander $commander = null)
    {
        $this->name = $name;
        $this->queueInstance = $queue;

        if(is_null($commander)) {
            $commander = new Commander($queue);
        }
        $this->commander = $commander;
    }

    /**
     * run the worker
     */
    public function run()
    {
        swoole_set_process_name($this->getMasterProcessName());

        $this->_status = self::STATUS_RUNNING;

        $this->forkWorkers();

        $this->installMasterSignals();
    }

    /**
     * get the worker name
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * set the queues
     * @param array $queues
     * @return $this
     */
    public function setQueues($queues)
    {
        $this->queues = $queues;

        return $this;
    }

    /**
     * set the worker numbers
     * @param int $workers
     * @return $this
     */
    public function setWorkers($workers)
    {
        $this->workers = $workers;

        return $this;
    }

    /**
     * set sleep interval
     * @param int $sleep
     * @return $this
     */
    public function setSleep($sleep)
    {
        $this->sleep = $sleep;

        return $this;
    }

    /**
     * set max jobs
     * @param $maxJobs
     * @return $this
     */
    public function setMaxJobs($maxJobs)
    {
        $this->maxJobs = $maxJobs;

        return $this;
    }

    /**
     * set commander
     * @param Commander $commander
     * @return $this
     */
    public  function setCommander(Commander $commander)
    {
        $this->commander = $commander;

        return $this;
    }

    /**
     * get the master process name
     * @return string
     */
    protected function getMasterProcessName()
    {
        return 'queue:master:' .$this->name;
    }

    /**
     * get the worker process name
     * @return string
     */
    protected function getWorkerProcessName()
    {
        return 'queue:worker:' . $this->name;
    }

    /**
     * register master signal handlers
     */
    protected function installMasterSignals()
    {
        $worker = $this;
        swoole_process::signal(SIGTERM, function() use($worker) {
            $worker->stop();
        });
        swoole_process::signal(SIGINT, function() use($worker) {
            $worker->stop();
        });
        swoole_process::signal(SIGQUIT, function() use($worker) {
            $worker->stop();
        });
        swoole_process::signal(SIGUSR1, function() use($worker) {
            $worker->pause();
        });
        swoole_process::signal(SIGUSR2, function() use($worker) {
            $worker->unpause();
        });
        swoole_process::signal(SIGCHLD, function() use($worker) {
            $worker->handleWorkerExit();
        });
    }

    /**
     * register worker signal handlers
     */
    protected function installWorkerSignals()
    {
        pcntl_signal(SIGTERM, [$this, 'stopWorker']);
        pcntl_signal(SIGINT, [$this, 'stopWorker']);
        pcntl_signal(SIGQUIT, [$this, 'stopWorker']);
    }

    /**
     * stop running
     */
    protected function stop()
    {
        $this->stopAllWorkers();

        $this->_status = self::STATUS_STOPPED;
        exit;
    }

    /**
     * pause
     */
    protected function pause()
    {
        $this->stopAllWorkers();

        $this->_status = self::STATUS_PAUSED;
    }

    /**
     * unpause
     */
    protected function unpause()
    {
        $this->forkWorkers();

        $this->_status = self::STATUS_RUNNING;
    }

    /**
     * kill all workers
     */
    protected function stopAllWorkers()
    {
        foreach($this->_workerProcesses as $pid => $process) {
            swoole_process::kill($pid, SIGTERM);
            unset($this->_workerProcesses[$pid]);
            $this->_workers--;
        }
    }

    /**
     * stop one worker
     */
    protected function stopWorker()
    {
        $this->_status = self::STATUS_STOPPED;
    }

    /**
     * handler worker exit signal
     */
    protected function handleWorkerExit()
    {
        $result = swoole_process::wait();
        foreach ($this->_workerProcesses as $key => $value) {
            if ($key == $result['pid']) {
                unset($this->_workerProcesses[$key]);
                $this->_workers--;
            }
        }

        if(!$result['signal']) {
            $this->forkWorkers();
        }
    }

    /**
     * fork workers
     */
    protected function forkWorkers()
    {
        while ($this->_workers < $this->workers) {
            $this->createProcess();
        }
    }

    /**
     * create a worker process
     * @return bool
     */
    protected function createProcess()
    {
        $worker = $this;

        $process = new swoole_process(function (swoole_process $process) use ($worker) {
            swoole_set_process_name($worker->getWorkerProcessName());

            $worker->installWorkerSignals();

            $processedJobs = 0;

            while ($processedJobs < $worker->maxJobs && $worker->_status == self::STATUS_RUNNING) {
                $job = null;
                foreach ($worker->queues as $queue) {
                    $job = $worker->queueInstance->pop($queue);
                    if ($job instanceof AbstractJob) {
                        break;
                    }
                }

                if ($job instanceof AbstractJob) {
                    $worker->commander->handle($job);
                } else {
                    sleep($worker->sleep);
                }

                pcntl_signal_dispatch();
            }
        }, false, false);
        $pid = $process->start();
        if($pid === false) {
            return false;
        }

        $this->_workers++;
        $this->_workerProcesses[$pid] = $process;
        return true;
    }
}
