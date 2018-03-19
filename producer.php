<?php
require_once __DIR__ . '/vendor/autoload.php';

class MyJob extends \Wangjian\Queue\Job\AbstractJob
{
    protected $maxTries = 3;

    protected $queue = 'test';

    protected $name = 'myjob';

    public function run()
    {
        echo 'Hello World';
        throw new Exception('');
    }
}

$client = new \Predis\Client([
    'schema' => 'tcp',
    'host' => '172.17.0.4',
    'port' => 6379,
    'database' => 0
]);

$queue = new \Wangjian\Queue\RedisQueue($client, 'test');
$queue->push(new MyJob());
