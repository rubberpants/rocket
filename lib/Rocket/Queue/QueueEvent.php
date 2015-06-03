<?php

namespace Rocket\Queue;

use Symfony\Component\EventDispatcher\Event;

class QueueEvent extends Event
{
    protected $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function getQueue()
    {
        return $this->queue;
    }
}
