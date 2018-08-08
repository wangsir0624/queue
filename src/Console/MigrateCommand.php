<?php
namespace Wangjian\Queue\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wangjian\Queue\MigrateInterface;

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

        $queue = $this->createQueueInstance();

        if($queue instanceof MigrateInterface) {
            $queue->migrate();
        }
    }
}