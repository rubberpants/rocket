<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class JobsListCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('jobs:list')
            ->setDescription('List active jobs in the system')
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
            $jobs = $this->getRocket()->getPlugin('aggregate')->getAllWaitingJobs();
        } elseif ($input->getOption('running')) {
            $jobs = $this->getRocket()->getPlugin('aggregate')->getAllRunningJobs();
        } elseif ($input->getOption('scheduled')) {
            $jobs = $this->getRocket()->getPlugin('aggregate')->getAllScheduledJobs();
        } else {
            throw new \Exception('A status must be specified');
        }

        $output->writeln(json_encode($jobs, JSON_PRETTY_PRINT));
    }
}
