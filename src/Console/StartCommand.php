<?php
namespace Wangjian\Queue\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Wangjian\Queue\Worker;

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
        exec("ps -ef | awk '$8 == \"queue:master:$workerName\" {print $2}'", $out, $return);
        if(!empty($out)) {
            $output->writeln('<info>the worker is already running...</info>');
            exit(1);
        }

        //load the bootstrap file
        $bootstrap = $input->getOption('bootstrap');
        if(!is_null($bootstrap)) {
            if($bootstrapFile = realpath($bootstrap)) {
                require_once $bootstrapFile;
            } else {
                $output->writeln('<info>the bootstrap file does not exist...</info>');
                exit(1);
            }
        }

        parent::execute($input, $output);

        try {
            $queue = $this->createQueueInstance();
        } catch (Exception $e) {
            $output->writeln('<info>' . $e->getMessage() . '</info>');
            exit(1);
        }

        $worker = new Worker($workerName, $queue);

        //set the worker numbers
        $workerNums = $this->getConfig('QUEUE_WORKERS', 4);
        $worker->setWorkers($workerNums);

        //set the worker max jobs
        $maxJobs = $this->getConfig('QUEUE_MAX_JOBS', 10000);
        $worker->setMaxJobs($maxJobs);

        //set the sleep interval
        $sleepInterval = $this->getConfig('QUEUE_SLEEP_INTERVAL', 5);
        $worker->setSleep($sleepInterval);

        //set the queues
        $queues = explode(',', $this->getConfig('QUEUE_WORK_QUEUES', 'default'));
        $worker->setQueues($queues);

        $worker->run();
    }
}