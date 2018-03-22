<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = new \Predis\Client([
    'schema' => 'tcp',
    'host' => '172.17.0.3',
    'port' => 6379,
    'database' => 0
]);

$queue = new \Wangjian\Queue\RedisQueue($client, 'test');
$queue->push(new \Wangjian\Queue\Job\TestJob());
