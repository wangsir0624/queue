<?php
namespace Wangjian\Queue;

use swoole_process;

class Worker
{
    /**
     * restart policy constants
     * @const int
     */
    const RESTART_NONE = 0b0;
    const RESTART_ON_EXIT = 0b1;
    const RESTART_ON_ERROR = 0b10;
    const RESTART_ON_SIGNAL = 0b100;
    const RESTART_ALL = 0b11111111;

    /**
     * the worker name
     * @var string
     */
    protected $name;

    /**
     * restart policy
     * @var int
     */
    protected $restartPolicy;

    /**
     * the worker numbers
     * @var int
     */
    protected $workers = 4;

    /**
     * max error times in error interval. if exceed the maximum error frequency, the master will not fork new workers
     * @var int
     */
    protected $maxErrorTimes = 10;

    /**
     * error interval
     * @var int
     */
    protected $errorInterval = 1;

    /**
     * work
     * @var callable
     */
    private $_work;

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
     * whether to stop forking new workers on error
     * @var bool
     */
    private $_disableRestartOnError = false;

    /**
     * the error timestamps
     * @var array
     */
    private $_errors = [];

    /**
     * don't restart the worker killed by the master
     * @var array
     */
    private $_noRestartProcesses = [];

    /**
     * whether the worker is bootstrapped
     */
    private $_isBooted = false;

    /**
     * Worker constructor
     * @param string $name  the worker name
     * @param callable $work
     * @param int $restartPolicy
     */
    public function __construct($name, $workers = 4, $restartPolicy = self::RESTART_NONE)
    {
        $this->name = $name;
        $this->workers = $workers;
        $this->restartPolicy = $restartPolicy;
    }

    /**
     * run the worker
     */
    public function run(callable $work)
    {
        $this->bootstrap();
        $this->_work = $work;
        $this->forkWorkers();
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
     * @param $maxErrorTimes
     * @param int $errorInterval
     * @return $this
     */
    public function setMaxErrorFrequency($maxErrorTimes, $errorInterval = 1)
    {
        $this->maxErrorTimes = $maxErrorTimes;
        $this->errorInterval = $errorInterval;

        return $this;
    }

    /**
     * bootstrap the worker
     */
    protected function bootstrap()
    {
        if($this->_isBooted) {
            return;
        }

        swoole_set_process_name($this->name . ':master');
        $this->installSignals();
        $this->_isBooted = true;
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
    public function stopAllWorkers()
    {
        foreach($this->_workerProcesses as $pid => $process) {
            $this->stopWorker($pid);
        }
    }

    public function stopWorker($pid)
    {
        if(!empty($this->_workerProcesses[$pid])) {
            swoole_process::kill($pid, SIGTERM);
            unset($this->_workerProcesses[$pid]);
            $this->_workers--;
            $this->_noRestartProcesses[$pid] = true;
        }
    }

    /**
     * handler worker exit signal
     */
    protected function handleWorkerExit()
    {
        $result = swoole_process::wait();

        if(!empty($this->_noRestartProcesses[$result['pid']])) {
            unset($this->_noRestartProcesses[$result['pid']]);
            return;
        }

        if($result['signal'] > 0) {
            if($this->restartPolicy & self::RESTART_ON_SIGNAL) {
                $this->forkOneWorker();
            }
        } else {
            if($result['code'] > 0) {
                $this->_errors[] = microtime(true);

                if($this->restartPolicy & self::RESTART_ON_ERROR && !$this->_disableRestartOnError) {
                    if($this->tooManyErrors()) {
                        //if exceed the maximum error frequency, stop forking new workers on error
                        $this->_disableRestartOnError = true;
                    } else {
                        $this->forkOneWorker();
                    }
                }
            } else {
                if($this->restartPolicy & self::RESTART_ON_EXIT) {
                    $this->forkOneWorker();
                }
            }
        }
    }

    /**
     * fork workers
     */
    protected function forkWorkers()
    {
        while ($this->_workers < $this->workers) {
            $this->forkOneWorker();
        }
    }

    /**
     * create a worker process
     * @return bool
     */
    protected function forkOneWorker()
    {
        $worker = $this;

        $process = new swoole_process(function (swoole_process $process) use ($worker) {
            swoole_set_process_name($worker->name . ':worker');

            call_user_func($worker->_work, $process);
        }, false, false);
        $pid = $process->start();
        if($pid === false) {
            return false;
        }

        $this->_workers++;
        $this->_workerProcesses[$pid] = $process;
        return true;
    }

    /**
     * whether exceed the maximum error frequency
     * @return bool
     */
    protected function tooManyErrors()
    {
        $count = count($this->_errors);
        if($count < $this->maxErrorTimes) {
            return false;
        }

        return ($this->_errors[$count - 1] - $this->_errors[$count - $this->maxErrorTimes]) <= $this->errorInterval;
    }
}
