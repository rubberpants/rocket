<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemPauseCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('system:pause')
            ->setDescription('Prevent any new jobs from being delivered to a worker')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getRocket()->getPlugin('pump')->haltProcessing();
    }
}
