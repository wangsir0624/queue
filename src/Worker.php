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
     * exit code when worker processed enough jobs
     * @const int
     */
    const MAX_JOBS_EXIT_CODE = 100;

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
    protected $maxJobs = 10000;

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
     * callbacks on success
     * @var array
     */
    protected $successCallbacks = [];

    /**
     * callbacks when skip retry
     * @var array
     */
    protected $skipRetryCallbacks = [];

    /**
     * callbacks on retry
     * @var array
     */
    protected $retryCallbacks = [];

    /**
     * callbacks on failure
     * @var array
     */
    protected $failCallbacks = [];

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
    public function __construct($name, QueueInterface $queue)
    {
        $this->name = $name;
        $this->queueInstance = $queue;
    }

    /**
     * run the worker
     */
    public function run()
    {
        swoole_set_process_name($this->getMasterProcessName());

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
     * register success callbacks
     * @return bool
     */
    public function onSuccess()
    {
        $argList = func_get_args();
        return call_user_func_array([$this, 'on'], array_merge(['success'], $argList));
    }

    /**
     * register skip retry callbacks
     * @return bool
     */
    public function onSkipRetry()
    {
        $argList = func_get_args();
        return call_user_func_array([$this, 'on'], array_merge(['skipRetry'], $argList));
    }

    /**
     * register retry callbacks
     * @return bool
     */
    public function onRetry()
    {
        $argList = func_get_args();
        return call_user_func_array([$this, 'on'], array_merge(['retry'], $argList));
    }

    /**
     * register fail callbacks
     * @return bool
     */
    public function onFail()
    {
        $argList = func_get_args();
        return call_user_func_array([$this, 'on'], array_merge(['fail'], $argList));
    }

    /**
     * register callbacks
     * @return bool
     * @throws Exception
     */
    protected function on()
    {
        $argCount = func_num_args();
        $argList = func_get_args();

        if($argCount < 2) {
            throw new Exception('this function needs at least two arguments');
        } else if($argCount == 2) {
            $event = $argList[0];
            $callbacks = $argList[1];

            if(is_array($callbacks)) {
                $callbackName = $event . 'Callbacks';
                $this->$callbackName = array_merge($this->$callbackName, $callbacks);
            } else {
                $callbackName = $event . 'Callbacks';
                $this->$callbackName = array_merge($this->$callbackName, ['global' => $callbacks]);
            }
        } else {
            $event = $argList[0];
            $queue = $argList[1];
            $callback = $argList[2];

            $callbackName = $event . 'Callbacks';
            $this->$callbackName = array_merge($this->$callbackName, [$queue => $callback]);
        }

        return true;
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
        swoole_process::signal(SIGCHLD, function() use($worker) {
            $worker->handleWorkerExit();
        });
    }

    /**
     * register worker signal handlers
     */
    protected function installWorkerSignals()
    {
        $callable = function() {
            exit;
        };

        pcntl_signal(SIGTERM, $callable);
        pcntl_signal(SIGINT, $callable);
        pcntl_signal(SIGQUIT, $callable);
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

        if($result['code'] == self::MAX_JOBS_EXIT_CODE) {
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
            while ($processedJobs < $worker->maxJobs) {
                $job = null;
                foreach ($worker->queues as $queue) {
                    $job = $worker->queueInstance->pop($queue);
                    if ($job instanceof AbstractJob) {
                        break;
                    }
                }

                if ($job instanceof AbstractJob) {
                    try {
                        if ($job->run() === false) {
                            throw new Exception('job run failed');
                        }

                        if(!is_null($callback = $this->getSuccessCallback($job->getQueue()))) {
                            call_user_func($callback, $job);
                        }
                    } catch (SkipRetryException $e) {
                        if(!is_null($callback = $this->getSkipRetryCallback($job->getQueue()))) {
                            call_user_func($callback, $job, $e);
                        }
                    } catch (Exception $e) {
                        $job->failed();
                        if($job->shouldRetry()) {
                            $this->queueInstance->retry($job);

                            if(!is_null($callback = $this->getRetryCallback($job->getQueue()))) {
                                call_user_func($callback, $job, $e);
                            }
                        } else {
                            if(!is_null($callback = $this->getFailCallback($job->getQueue()))) {
                                call_user_func($callback, $job, $e);
                            }
                        }
                    }

                    $processedJobs++;
                } else {
                    sleep($worker->sleep);
                }

                pcntl_signal_dispatch();
            }

            exit(self::MAX_JOBS_EXIT_CODE);
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
     * get success callback on a queue
     * @param string $queue
     * @return callable|null
     */
    protected function getSuccessCallback($queue)
    {
        return $this->getCallback('success', $queue);
    }

    /**
     * get skip retry callback on a queue
     * @param string $queue
     * @return callable|null
     */
    protected function getSkipRetryCallback($queue)
    {
        return $this->getCallback('skipRetry', $queue);
    }

    /**
     * get retry callback on a queue
     * @param string $queue
     * @return callable|null
     */
    protected function getRetryCallback($queue)
    {
        return $this->getCallback('retry', $queue);
    }

    /**
     * get fail callback on a queue
     * @param string $queue
     * @return callable|null
     */
    protected function getFailCallback($queue)
    {
        return $this->getCallback('fail', $queue);
    }

    /**
     * get callback registered on a queue
     * @param string $event
     * @param string $queue
     * @return callable|null
     */
    protected function getCallback($event, $queue)
    {
        $callbackName = $event . 'Callbacks';
        if(isset($this->$callbackName[$queue])) {
            return $this->$callbackName[$queue];
        }

        if(isset($this->$callbackName['global'])) {
            return $this->$callbackName[$queue];
        }

        return null;
    }
}
