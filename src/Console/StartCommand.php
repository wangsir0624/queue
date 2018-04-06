<?php
namespace Wangjian\Queue\Console;

use Predis\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wangjian\Queue\RedisQueue;
use Exception;
use Wangjian\Queue\Traits\CommandConfigTrait;
use Wangjian\Queue\Worker;

class StartCommand extends Command
{
    use CommandConfigTrait;

    protected function configure()
    {
        parent::configure();

        $this->setName('start')
            ->setDescription('start the queue consumer worker')
            ->addArgument('name', InputArgument::REQUIRED, 'the worker name')
            ->addOption('bootstrap', 'b', InputOption::VALUE_OPTIONAL, 'bootstrap file')
            ->prepareConfigOption();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //check whether the worker is already running
        $workerName = $input->getArgument('name');
        exec("ps -ef | awk '$8 == \"queue:master:$workerName\" {print $2}'", $out, $return);
        if(!empty($out)) {
            $output->writeln('<info>the worker is already running...</info>');
            exit;
        }

        //load the bootstrap file
        $bootstrap = $input->getOption('bootstrap');
        if(!is_null($bootstrap)) {
            if($bootstrapFile = realpath($bootstrap)) {
                require_once $bootstrapFile;
            } else {
                $output->writeln('<info>the bootstrap file does not exist...</info>');
                exit;
            }
        }

        //parse the config files
        $config = $input->getOption('config');
        if(!is_null($config)) {
            if($configFile = realpath($config)) {
                if(($configs = parse_ini_file($configFile)) !== false) {
                    $this->configVariables = $configs;
                } else {
                    $output->writeln('<info>the config file format is incorrect...</info>');
                    exit;
                }
            } else {
                $output->writeln('<info>the config file does not exist...</info>');
                exit;
            }
        }

        //parse the environment variables
        foreach($input->getOption('environment') as $env) {
            if(!$this->checkEnvironmentVariable($env)) {
                $output->writeln('<info>the environment variables are not incorrect...</info>');
                exit;
            }

            list($key, $value) = explode('=', $env, 2);
            $this->environmentVariables[$key] = $value;
        }

        try {
            $queue = $this->createQueueInstance();
        } catch (Exception $e) {
            $output->writeln('<info>' . $e->getMessage() . '</info>');
            exit;
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