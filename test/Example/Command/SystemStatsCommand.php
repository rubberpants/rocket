<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemStatsCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('system:stats')
            ->setDescription('Displays statistics for the system')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stats = $this->getRocket()->getPlugin('statistics')->getAllStatistics();

        $output->write(json_encode($stats, JSON_PRETTY_PRINT));
    }
}
