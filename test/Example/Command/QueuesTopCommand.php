<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Rocket\Plugin\Top\TopPlugin;

class QueuesTopCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('queues:top')
            ->setDescription('List the top 10 queues by jobs waiting or jobs running')
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
            $queues = $this->getRocket()->getPlugin('top')->getTopQueues(TopPlugin::METRIC_WAITING);
        } elseif ($input->getOption('running')) {
            $queues = $this->getRocket()->getPlugin('top')->getTopQueues(TopPlugin::METRIC_RUNNING);
        } else {
            throw new \Exception('A status must be specified');
        }

        $output->writeln(json_encode($queues, JSON_PRETTY_PRINT));
    }
}
