<?php

namespace Rocket\Plugin;

use Symfony\Component\EventDispatcher\EventDispatcher;

trait EventTrait
{
    protected $eventDispatcher;

    public function setEventDispatcher(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }
}
