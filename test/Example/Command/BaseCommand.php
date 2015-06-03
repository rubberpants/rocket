<?php

namespace Example\Command;

use Symfony\Component\Console\Command\Command;
use Rocket\RocketInterface;

class BaseCommand extends Command
{
    protected $rocket;

    public function __construct(RocketInterface $rocket)
    {
        parent::__construct();
        $this->rocket = $rocket;
    }

    public function getRocket()
    {
        return $this->rocket;
    }
}
