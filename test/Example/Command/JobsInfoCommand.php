<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JobsInfoCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('jobs:info')
            ->setDescription('Return information about a job')
            ->addArgument(
                'job_id',
                InputArgument::REQUIRED,
                'The id of the job to get info for'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $job = $this->getRocket()->getJob(
            $input->getArgument('job_id')
        );

        $output->writeln(json_encode($job->getHash()->getFields(), JSON_PRETTY_PRINT));
    }
}
