<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemFlushCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('system:flush')
            ->setDescription('Delete all data in the system')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getRocket()->getRedis()->getClient()->flushdb();
    }
}
