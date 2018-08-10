<?php
namespace Wangjian\Queue\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Wangjian\Queue\Consumer;
use Wangjian\Queue\Traits\Daemonize;
use Wangjian\Queue\Worker;
use Wangjian\Queue\Job\AbstractJob;
use Wangjian\Queue\Exception\SkipRetryException;
use swoole_process;

class StartCommand extends ConfigCommandBase
{
    use Daemonize;

    protected function configure()
    {
        parent::configure();

        $this->setName('start')
            ->setDescription('start the queue consumer worker')
            ->addArgument('name', InputArgument::REQUIRED, 'the worker name')
            ->addOption('bootstrap', 'b', InputOption::VALUE_OPTIONAL, 'bootstrap file')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'whether run as a daemon');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //check whether the worker is already running
        $workerName = $input->getArgument('name');
        exec("ps -ef | awk '$8 == \"queue:$workerName:master\" {print $2}'", $out, $return);
        if(!empty($out)) {
            $output->writeln('<info>the worker is already running...</info>');
            exit(1);
        }

        //get the bootstrap file
        $bootstrap = $input->getOption('bootstrap');
        if(!is_null($bootstrap)) {
            if(!($bootstrap = realpath($bootstrap))) {
                $output->writeln('<info>the bootstrap file does not exist...</info>');
                exit(1);
            }
        }

        if($input->getOption('daemon')) {
            $this->daemonize();
        }

        $this->doExecute($input, $output, $workerName, $bootstrap);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output, $workerName, $bootstrap, $worker = null)
    {
        $this->loadConfig($input, $output);

        $command = $this;

        $workers = $this->getConfig('QUEUE_WORKERS', 4);
        $worker = $worker ? $worker->setWorkers($workers) : new Worker("queue:$workerName", $workers, Worker::RESTART_ON_EXIT | Worker::RESTART_ON_ERROR);
        $worker->setMaxErrorFrequency(
            $this->getConfig('QUEUE_MAX_ERROR_TIMES', 10),
            $this->getConfig('QUEUE_ERROR_INTERVAL', 1)
        );
        $worker->run(function(swoole_process $process) use ($command, $bootstrap) {
            if(!empty($bootstrap)) {
                require_once $bootstrap;
            }

            //register worker signals
            $callable = function() {
                exit;
            };
            pcntl_signal(SIGTERM, $callable);
            pcntl_signal(SIGINT, $callable);
            pcntl_signal(SIGQUIT, $callable);

            $maxJobs = $command->getConfig('QUEUE_MAX_JOBS', 10000);
            $queues = explode(',', $command->getConfig('QUEUE_WORK_QUEUES', 'default'));
            $sleepInterval = $command->getConfig('QUEUE_SLEEP_INTERVAL', 5);
            $queueInstance = $this->createQueueInstance();
            $consumer = new Consumer($queueInstance, $queues);
            $processedJobs = 0;
            while ($processedJobs < $maxJobs) {
                $job = $consumer->consume();

                if ($job instanceof AbstractJob) {
                    try {
                        if ($job->run() === false) {
                            throw new Exception('job run failed');
                        }
                    } catch (SkipRetryException $e) {

                    } catch (Exception $e) {
                        $job->failed();
                        if ($job->shouldRetry()) {
                            $queueInstance->retry($job);
                        }
                    }

                    $processedJobs++;
                } else {
                    sleep($sleepInterval);
                }
                pcntl_signal_dispatch();
            }
        });

        swoole_process::signal(SIGUSR1, function() use ($command, $worker, $input, $output, $workerName, $bootstrap) {
            $worker->stopAllWorkers();
            $command->doExecute($input, $output, $workerName, $bootstrap, $worker);
        });
    }
}