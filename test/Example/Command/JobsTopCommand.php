<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Rocket\Plugin\Top\TopPlugin;

class JobsTopCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('jobs:top')
            ->setDescription('List the top 10 jobs by time waiting or time running')
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('waiting')) {
            $jobs = $this->getRocket()->getPlugin('top')->getTopJobs(TopPlugin::METRIC_WAITING);
        } elseif ($input->getOption('running')) {
            $jobs = $this->getRocket()->getPlugin('top')->getTopJobs(TopPlugin::METRIC_RUNNING);
        } else {
            throw new \Exception('A status must be specified');
        }

        $output->writeln(json_encode($jobs, JSON_PRETTY_PRINT));
    }
}
