<?php

namespace Example\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemResumeCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('system:resume')
            ->setDescription('Resume delivering jobs to workers')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getRocket()->getPlugin('pump')->resumeProcessing();
    }
}
