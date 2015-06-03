<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Rocket\Worker\WorkerCommandException;

class JobsWorkCommand extends BaseCommand
{
    protected $active;

    protected function configure()
    {
        $this
            ->setName('jobs:work')
            ->setDescription('Accept jobs for processing')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of this worker'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $worker = $this->getRocket()->getWorker($input->getArgument('name'));

        while (true) {
            try {
                if ($worker->getNewJob(getmypid())) {
                    $worker->startCurrentJob();

                    $job = json_decode($worker->getCurrentJob()->getJob(), true);

                    sleep($job['runtime']);

                    if (mt_rand(1, 100) == 100) {
                        $worker->failCurrentJob('Failed');
                    } else {
                        $worker->completeCurrentJob();
                    }
                }
            } catch (WorkerCommandException $e) {
                if ($e->getCommand() == 'STOP') {
                    break;
                }
            }
        }
    }
}
