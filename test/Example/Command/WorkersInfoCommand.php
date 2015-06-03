<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class WorkersInfoCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('workers:info')
            ->setDescription('Show information about a worker')
            ->addArgument(
                'worker_name',
                InputArgument::REQUIRED,
                'The name of the worker to get info for'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $worker = $this->getRocket()->getWorker($input->getArgument('worker_name'));
        $output->writeln(json_encode($worker->getHash()->getFields(), JSON_PRETTY_PRINT));
    }
}
