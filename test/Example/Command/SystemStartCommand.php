<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SystemStartCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('system:start')
            ->setDescription('Start workers')
            ->addArgument(
                'workers',
                InputArgument::REQUIRED,
                'The number of workers to start'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        for ($n = 0; $n < $input->getArgument('workers'); $n++) {
            $commandLine = sprintf('php %s/../example jobs:work worker-%d > %s/../worker-%s.log', __DIR__, $n, __DIR__, $n);
            $worker = new Process($commandLine);
            $worker->start();
        }
    }
}
