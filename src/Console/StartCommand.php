<?php
namespace Wangjian\Queue\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Wangjian\Queue\Worker;
use Wangjian\Queue\Job\AbstractJob;
use Wangjian\Queue\Exception\SkipRetryException;
use swoole_process;

class StartCommand extends ConfigCommandBase
{
    protected function configure()
    {
        parent::configure();

        $this->setName('start')
            ->setDescription('start the queue consumer worker')
            ->addArgument('name', InputArgument::REQUIRED, 'the worker name')
            ->addOption('bootstrap', 'b', InputOption::VALUE_OPTIONAL, 'bootstrap file');
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

        //load the bootstrap file
        $bootstrap = $input->getOption('bootstrap');
        if(!is_null($bootstrap)) {
            if(!($bootstrap = realpath($bootstrap))) {
                $output->writeln('<info>the bootstrap file does not exist...</info>');
                exit(1);
            }
        }

        parent::execute($input, $output);

        $command = $this;
        $worker = new Worker("queue:$workerName", function(swoole_process $process) use ($command, $bootstrap, $input, $output) {
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
            $processedJobs = 0;
            while ($processedJobs < $maxJobs) {
                $job = null;
                foreach ($queues as $queue) {
                    $job = $queueInstance->pop($queue);
                    if ($job instanceof AbstractJob) {
                        break;
                    }
                }
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
        $worker->setWorkers($this->getConfig('QUEUE_WORKERS', 4));
        $worker->setMaxErrorFrequency(
            $this->getConfig('QUEUE_MAX_ERROR_TIMES', 10),
            $this->getConfig('QUEUE_ERROR_INTERVAL', 1)
        );
        $worker->run();
    }
}