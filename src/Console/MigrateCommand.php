<?php
namespace Wangjian\Queue\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends ConfigCommandBase
{
    protected function configure()
    {
        parent::configure();

        $this->setName('migrate')
            ->setDescription('migrate the queue database');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input, $output);

        switch($this->getConfig('QUEUE_ADAPTER', 'redis')) {
            case 'redis':
                break;
            case 'mysql':
                $pdo = $this->createPdo();

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