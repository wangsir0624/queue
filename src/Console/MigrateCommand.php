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
use PDO;

class MigrateCommand extends Command
{
    use CommandConfigTrait;

    protected function configure()
    {
        parent::configure();

        $this->setName('migrate')
            ->setDescription('migrate the queue database')
            ->prepareConfigOption();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //load the configs
        $this->loadConfigs($input, $output);

        switch($this->getConfig('QUEUE_ADAPTER', 'redis')) {
            case 'redis':
                break;
            case 'mysql':
                $dsn = 'mysql:host=%s;port=%s;dbname=%s';
                $pdo = new PDO(sprintf(
                    $dsn,
                    $this->getConfig('QUEUE_MYSQL_HOST', '127.0.0.1'),
                    $this->getConfig('QUEUE_MYSQL_PORT', 3306),
                    $this->getConfig('QUEUE_MYSQL_DATABASE', 'test')
                    ),
                    $this->getConfig('QUEUE_MYSQL_USERNAME'),
                    $this->getConfig('QUEUE_MYSQL_PASSWORD'),
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]
                );
                $pdo->exec('set names utf8');

                //check whether the queue table already exists
                $table = $this->getConfig('QUEUE_MYSQL_TABLENAME', 'queue');
                $sql = "SHOW TABLES LIKE '%s'";
                $sth = $pdo->query(sprintf($sql, $table));
                if($sth->rowCount() > 0) {
                    $output->writeln(sprintf('<info>the table %s already exists...</info>', $table));
                    exit(1);
                }

                //create the queue table
                $sql = <<<SQL
CREATE TABLE %s (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  queue VARCHAR(100) NOT NULL COMMENT '队列名称',
  job VARCHAR(1024) NOT NULL COMMENT 'job对象json字符串',
  run_at INT(11) UNSIGNED NOT NULL COMMENT 'job运行时间戳',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_queue_run_at(queue, run_at),
  PRIMARY KEY(id)
) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT '消息队列表'
SQL;

                $pdo->exec(sprintf($sql, $table));
                break;
            default:
                $output->writeln('<info>unsupported adapter...</info>');
        }
    }
}