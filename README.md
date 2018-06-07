# queue
消息队列库，支持多种消息驱动，具有完善的容错机制，命令执行失败时，可以在一段时间之后进行重试。

## Usage

### 使用redis驱动

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$pdo = new PDO('mysql:host=mysql;dbname=test', 'root', 'root');
$queue = new \Wangjian\Queue\MysqlQueue($pdo, 'queue');

$queue->push((new \Wangjian\Queue\Job\TestJob()));
```

### 使用mysql驱动

在使用mysql消息队列驱动之前，还必须先执行migrate命令完成表格迁移工作。

```bash
php bin/worker migrate  -c queue.ini 
```

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$redis = new Predis\Client([
    'schema' => $this->getConfig('QUEUE_REDIS_SCHEMA', 'tcp'),
    'host' => $this->getConfig('QUEUE_REDIS_HOST', '127.0.0.1'),
    'port' => $this->getConfig('QUEUE_REDIS_PORT', 6379),
    'database' => $this->getConfig('QUEUE_REDIS_DATABASE', 0)
]);

$queue = new \Wangjian\Queue\RedisQueue($redis, 'queue');

$queue->push((new \Wangjian\Queue\Job\TestJob()));
```

### 添加定时任务

```php
# 只要将任务的执行时间设置成未来的某个时刻，该任务就会成为定时任务
$queue->push((new \Wangjian\Queue\Job\TestJob())->setRunAt(time() + 10));  //十秒后执行该任务
```

> 定时任务执行时间并不一定精确，因为如果队列没有数据的时候，消费者进程会堵塞，因此，可能会有一些延迟，这个取决于QUEUE_SLEEP_INTERVAL配置项的值

### 消费者进程

开启消费者进程，就可以执行消息队列。开启之前，首先我们要对消费者进程进行配置，配置文件可以参考queue.ini.example。

```bash
php bin/worker start default -c queue.ini
```

执行如上命令，就可以开启一个名称为default，配置文件为queue.ini的消费者进程，除了使用配置文件外，还可以使用如下两种方式进行配置：

- 使用环境变量

```bash
export QUEUE_SLEEP_INTERVAL=3
```

- 使用-e参数
```bash
php bin/worker start default -c queue.ini -e "QUEUE_SLEEP_INTERVAL=3"
```

> 配置参数优先级为：-e参数 > 配置文件 > 环境变量

### 关闭消费者进程

```bash
# 关闭名称为default的消息队列消费者进程
php bin/worker stop default
```


