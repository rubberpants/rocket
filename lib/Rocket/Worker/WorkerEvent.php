<?php

namespace Rocket\Worker;

use Symfony\Component\EventDispatcher\Event;

class WorkerEvent extends Event
{
    protected $worker;

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

    public function getWorker()
    {
        return $this->worker;
    }
}
