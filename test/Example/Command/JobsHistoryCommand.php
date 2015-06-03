<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JobsHistoryCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('jobs:history')
            ->setDescription('Returns the history of a job')
            ->addArgument(
                'job_id',
                InputArgument::REQUIRED,
                'The id of the job to get history for'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $job = $this->getRocket()->getJob($input->getArgument('job_id'));

        foreach ($job->getHistory() as $entry) {
            $output->writeln((string) $entry);
        }
    }
}
