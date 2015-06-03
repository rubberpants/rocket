<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueuesStatsCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('queues:stats')
            ->setDescription('Displays statistics for a queue')
            ->addArgument(
                'queue_name',
                InputArgument::REQUIRED,
                'The name of the queue to get stats for'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getRocket()->getQueue($input->getArgument('queue_name'));

        $stats = $this->getRocket()->getPlugin('statistics')->getQueueStatistics($queue);

        $output->write(json_encode($stats, JSON_PRETTY_PRINT));
    }
}
