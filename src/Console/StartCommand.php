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
use Wangjian\Queue\Worker;

class StartCommand extends Command
{
    protected $configVariables = [];

    protected $environmentVariables = [];

    protected function configure()
    {
        parent::configure();

        $this->setName('start')
            ->setDescription('start the queue consumer worker')
            ->addArgument('name', InputArgument::REQUIRED, 'the worker name')
            ->addOption('bootstrap', 'b', InputOption::VALUE_OPTIONAL, 'bootstrap file')
            ->addOption('environment', 'e', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'environment variable')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'config file');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //check whether the worker is already running
        $workerName = $input->getArgument('name');
        exec("ps -ef | grep queue:master:$workerName", $out, $return);
        if(count($out) >= 3) {
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

    protected function createQueueInstance()
    {
        $queue = null;

        $queueAdapter = $this->getConfig('QUEUE_ADAPTER', 'redis');
        switch($queueAdapter) {
            case 'redis':
                $configs = [
                    'schema' => $this->getConfig('QUEUE_REDIS_SCHEMA', 'tcp'),
                    'host' => $this->getConfig('QUEUE_REDIS_HOST', '127.0.0.1'),
                    'port' => $this->getConfig('QUEUE_REDIS_PORT', 6379),
                    'database' => $this->getConfig('QUEUE_REDIS_DATABASE', 0)
                ];
                if(!is_null($password = $this->getConfig('QUEUE_REDIS_PASSWORD', null))) {
                    $configs['password'] = $password;
                }

                $redisClient = new Client($configs);
                $queue = new RedisQueue($redisClient, $this->getConfig('QUEUE_REDIS_PREFIX', ''));
                break;
            default:
                throw new Exception('unsupported queue adapter...');
        }

        return $queue;
    }

    protected function checkEnvironmentVariable($raw)
    {
        return preg_match("/^[a-zA-Z]\w*?\=[^\=]+$/", $raw);
    }

    protected function getConfig($name, $default = null)
    {
        if(isset($this->environmentVariables[$name])) {
            return $this->environmentVariables[$name];
        }

        if(isset($this->configVariables[$name])) {
            return $this->configVariables[$name];
        }

        if(($value = getenv($name)) !== false) {
            return $value;
        }

        return $default;
    }
}