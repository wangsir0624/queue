<?php
namespace Wangjian\Queue\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this->setName('stop')
            ->setDescription('stop the queue consumer worker')
            ->addArgument('name', InputArgument::REQUIRED, 'the worker name');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //check whether the worker is running
        $workerName = $input->getArgument('name');
        exec("ps -ef | awk '$8 == \"queue:master:$workerName\" {print $2}'", $out, $return);
        if(empty($out)) {
            $output->writeln('<info>the worker is not running...</info>');
            exit;
        }

        exec("ps -ef | awk '$8 == \"queue:master:$workerName\" {print $2}' | xargs kill", $out, $return);
    }
}