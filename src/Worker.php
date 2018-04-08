<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;
use swoole_process;
use Exception;
use Exception\SkipRetryException;
use ErrorException;

class Worker
{
    /**
     * the worker name
     * @var string
     */
    protected $name;

    /**
     * work
     * @var callable
     */
    protected $work;

    /**
     * the worker numbers
     * @var int
     */
    protected $workers = 4;

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
    public function __construct($name, callable $work)
    {
        $this->name = $name;
        $this->work = $work;
    }

    /**
     * run the worker
     */
    public function run()
    {
        swoole_set_process_name($this->name . ':master');

        $this->forkWorkers();

        $this->installSignals();
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
     * register master signal handlers
     */
    protected function installSignals()
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
        swoole_process::signal(SIGCHLD, function() use($worker) {
            $worker->handleWorkerExit();
        });
    }

    /**
     * stop running
     */
    protected function stop()
    {
        $this->stopAllWorkers();

        exit;
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
            swoole_set_process_name($worker->name . ':worker');

            call_user_func($worker->work, $process);
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
