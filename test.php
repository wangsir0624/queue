<?php
require_once __DIR__ . '/vendor/autoload.php';

class MyJob extends \Wangjian\Queue\Job\AbstractJob
{
    protected $queue = 'test';

    protected $name = 'test_job';

    protected $maxTries = 3;

    public function run()
    {
        echo 'Hello World' . PHP_EOL;
        throw new Exception('');
    }
}

$client = new \Predis\Client([
    'schema' => 'tcp',
    'host' => '127.0.0.1',
    'password' => 'root',
    'port' => 6379,
    'database' => 0
]);

$queue = new \Wangjian\Queue\RedisQueue($client);
$queue->push(new MyJob());
$job = $queue->pop('test');
var_dump($job);
$commander = new \Wangjian\Queue\Job\Commander($queue);
$commander->handle($job);
