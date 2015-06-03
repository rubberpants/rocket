<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class JobsQueueCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('jobs:queue')
            ->setDescription('Start queueing randomly generated jobs')
            ->addArgument(
                'number',
                InputArgument::REQUIRED,
                'The number of jobs to queue'
            )
            ->addOption(
                'no-router',
                   null,
                   InputOption::VALUE_NONE,
                'Do not use the router to decide which queues jobs go to'
            )
            ->addOption(
                'no-unique',
                null,
                InputOption::VALUE_NONE,
                'Allow identical jobs to being active at the same time'
            )
            ->addOption(
                'delay',
                null,
                InputOption::VALUE_REQUIRED,
                'Delay the queueing of the jobs by the specified number of seconds'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queued = 0;

        while ($queued++ < $input->getArgument('number')) {
            $queue = 'queue'.mt_rand(1, 4);

            $job = json_encode([
                'queue' => $queue,
                'runtime' => mt_rand(1, 3),
                'expedited' => mt_rand(1, 100) == 100 ? true : false,
            ]);

            if (!$input->getOption('no-router')) {
                $queue = $this->getRocket()->getPlugin('router')->applyRulesToJob($job);
            }

            if (!$input->getOption('no-unique')) {
                if ($this->getRocket()->getPlugin('unique')->getJobIdIfActive($job)) {
                    $output->writeln('Job was already queued');
                    continue;
                }
            }

            if ($delay = $input->getOption('delay')) {
                $start = new \DateTime();
                $start->add(new \DateInterval(sprintf('PT%dS', $delay)));
                $this->getRocket()->getQueue($queue)->scheduleJob($start, $job);
            } else {
                $this->getRocket()->getQueue($queue)->queueJob($job);
            }

            sleep(1);
        }
    }
}
