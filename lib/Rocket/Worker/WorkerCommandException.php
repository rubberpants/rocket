<?php

namespace Rocket\Worker;

class WorkerCommandException extends \Exception
{
    protected $command;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function getCommand()
    {
        return $this->command;
    }
}
