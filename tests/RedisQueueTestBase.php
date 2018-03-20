<?php
namespace Wangjian\Queue\Test;

use PHPUnit\Framework\TestCase;
use Wangjian\Queue\Job\Commander;
use Wangjian\Queue\RedisQueue;
use Predis\Client;

class RedisQueueTestBase extends TestCase
{
    protected $client;

    protected $queue;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->client = new Client([
            'schema' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0
        ]);

        $this->queue = new RedisQueue($this->client, 'test');
    }

    protected function setUp()
    {
        $this->client->flushdb();
    }
}
