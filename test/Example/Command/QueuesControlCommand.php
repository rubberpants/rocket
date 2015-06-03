<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueuesControlCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('queues:control')
            ->setDescription('Control inputs/outputs from a queue')
            ->addArgument(
                'queue_name',
                InputArgument::REQUIRED,
                'The name of the queue to control'
            )
            ->addOption(
                'disable',
                   null,
                   InputOption::VALUE_NONE,
                'Prevent new jobs from being accepted by this queue'
            )
            ->addOption(
                'enable',
                null,
                InputOption::VALUE_NONE,
                'Allow new jobs to be accepted by this queue'
            )
            ->addOption(
                'pause',
                null,
                InputOption::VALUE_NONE,
                'Prevent jobs from being processed from this queue'
            )
            ->addOption(
                'resume',
                null,
                InputOption::VALUE_NONE,
                'Allow jobs to be processed from this queue'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getRocket()->getQueue($input->getArgument('queue_name'));

        if ($input->getOption('disable')) {
            $queue->disable();
        } elseif ($input->getOption('enable')) {
            $queue->enable();
        } elseif ($input->getOption('pause')) {
            $queue->pause();
        } elseif ($input->getOption('resume')) {
            $queue->resume();
        } else {
            throw new \Exception('Operation must be specified');
        }
    }
}
