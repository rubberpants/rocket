<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkersListCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('workers:list')
            ->setDescription('List active workers in the system')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workers = $this->getRocket()->getPlugin('aggregate')->getAllWorkers();

        $output->writeln(json_encode($workers, JSON_PRETTY_PRINT));
    }
}
