<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;
use PDO;
use Exception;

class MysqlQueue implements QueueInterface
{
    protected $pdo;

    protected $table;

    public function __construct(PDO $pdo, $table = 'queue')
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('set names utf8');

        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function push(AbstractJob $job)
    {
        return $this->addJob($job, $job->getRunAt() > time() ? $job->getRunAt() : time());
    }

    public function pop($queue)
    {
        $sql1 = "SELECT * FROM %s WHERE queue='%s' AND run_at<=%d ORDER BY run_at ASC LIMIT 0, 1 FOR UPDATE";
        $sql2 = "DELETE FROM %s WHERE ID=%d";

        $this->pdo->beginTransaction();
        try {
            $row = $this->pdo->query(sprintf($sql1, $this->table, $queue, time()))->fetch(PDO::FETCH_ASSOC);
            if(!empty($row)) {
                $this->pdo->exec(sprintf($sql2, $this->table, $row['id']));
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        if(!empty($row)) {
            $job = @unserialize($row['job']);
            if ($job instanceof AbstractJob) {
                return $job;
            }
        }

        return null;
    }

    public function retry(AbstractJob $job)
    {
        if ($job->shouldRetry()) {
            return $this->addJob($job, $job->getRetryTime());
        }

        return false;
    }

    protected function addJob(AbstractJob $job, $runAt)
    {
        $sql = "INSERT INTO %s(queue, job, run_at) VALUES('%s', '%s', %d)";

        return (bool)$this->pdo->exec(sprintf(
            $sql,
            $this->table,
            $job->getQueue(),
            addslashes(serialize($job)),
            $runAt
        ));
    }
}