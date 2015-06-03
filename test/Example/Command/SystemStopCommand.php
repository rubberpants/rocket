<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemStopCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('system:stop')
            ->setDescription('Stop workers')
            ->addArgument(
                'workers',
                InputArgument::REQUIRED,
                'The number of workers to kill'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        for ($n = 0; $n < $input->getArgument('workers'); $n++) {
            $this->getRocket()->getWorker('worker-'.$n)->setCommand('STOP');
        }
    }
}
