<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueuesListCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('queues:list')
            ->setDescription('List all the queues')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(json_encode($this->getRocket()->getQueues(), JSON_PRETTY_PRINT));
    }
}
