<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JobTypeStatsCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('type:stats')
            ->setDescription('Displays statistics for a job type')
            ->addArgument(
                'job_type',
                InputArgument::REQUIRED,
                'The name of the type to get stats for'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $this->getRocket()->getQueue($input->getArgument('job_type'));

        $stats = $this->getRocket()->getPlugin('statistics')->getJobTypeStatistics($type);

        $output->write(json_encode($stats, JSON_PRETTY_PRINT));
    }
}
