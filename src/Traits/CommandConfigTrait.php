<?php
namespace Wangjian\Queue\Traits;

use Predis\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wangjian\Queue\RedisQueue;
use Exception;

trait CommandConfigTrait
{
    protected $configVariables = [];

    protected $environmentVariables = [];

    protected function prepareConfigOption()
    {

        $this->addOption('environment', 'e', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'environment variable')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'config file');
    }

    protected function loadConfigs(InputInterface $input, OutputInterface $output)
    {
        //parse the config files
        $config = $input->getOption('config');
        if(!is_null($config)) {
            if($configFile = realpath($config)) {
                if(($configs = parse_ini_file($configFile)) !== false) {
                    $this->configVariables = $configs;
                } else {
                    $output->writeln('<info>the config file format is incorrect...</info>');
                    exit(1);
                }
            } else {
                $output->writeln('<info>the config file does not exist...</info>');
                exit(1);
            }
        }

        //parse the environment variables
        foreach($input->getOption('environment') as $env) {
            if(!$this->checkEnvironmentVariable($env)) {
                $output->writeln('<info>the environment variables are not incorrect...</info>');
                exit(1);
            }

            list($key, $value) = explode('=', $env, 2);
            $this->environmentVariables[$key] = $value;
        }
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