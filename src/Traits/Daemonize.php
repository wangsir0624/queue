<?php
namespace Wangjian\Queue\Traits;

trait Daemonize
{
    public function daemonize()
    {
        umask(0);

        if(pcntl_fork() != 0) exit;

        posix_setsid();

        if(pcntl_fork() != 0) exit;
    }
}