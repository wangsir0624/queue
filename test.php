<?php
require_once __DIR__ . '/vendor/autoload.php';

class MyJob extends \Wangjian\Queue\Job\AbstractJob
{
    protected $maxTries = 3;

    public function queue()
    {
        return 'test';
    }

    public function run()
    {
        throw new Exception('');
        echo 'Hello World' . PHP_EOL;
    }
}

$client = new \Predis\Client([
    'schema' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0
]);

$queue = new \Wangjian\Queue\RedisQueue($client, 'test');
$queue->push(new MyJob());
$job = $queue->pop('test');
var_dump($job);
$commander = new \Wangjian\Queue\Job\Commander($queue);
$commander->handle($job);
