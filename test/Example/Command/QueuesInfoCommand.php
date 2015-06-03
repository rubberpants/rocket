<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueuesInfoCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('queues:info')
            ->setDescription('Return information about a queue')
            ->addArgument(
                'queue_name',
                InputArgument::REQUIRED,
                'The name of the queue to get info for'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getRocket()->getQueue($input->getArgument('queue_name'));
        $output->writeln(json_encode($queue->getInfo(), JSON_PRETTY_PRINT));
    }
}
