<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class JobsCountCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('jobs:count')
            ->setDescription('Number of active jobs in the system')
            ->addOption(
                'waiting',
                null,
                InputOption::VALUE_NONE,
                'Show waiting jobs'
            )
            ->addOption(
                'running',
                null,
                InputOption::VALUE_NONE,
                'Show running jobs'
            )
            ->addOption(
                'scheduled',
                null,
                InputOption::VALUE_NONE,
                'Show scheduled jobs'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('waiting')) {
            $count = $this->getRocket()->getPlugin('aggregate')->getAllWaitingJobCount();
        } elseif ($input->getOption('running')) {
            $count = $this->getRocket()->getPlugin('aggregate')->getAllRunningJobCount();
        } elseif ($input->getOption('scheduled')) {
            $count = $this->getRocket()->getPlugin('aggregate')->getAllScheduledJobCount();
        } else {
            throw new \Exception('A status must be specified');
        }

        $output->writeln($count);
    }
}
