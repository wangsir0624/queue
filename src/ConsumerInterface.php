<?php
namespace Wangjian\Queue;

use Wangjian\Queue\Job\AbstractJob;

interface ConsumerInterface
{
    /**
     * @return AbstractJob|null
     */
    public function consume();
}