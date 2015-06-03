<?php

namespace Rocket\Redis;

use Symfony\Component\EventDispatcher\Event;

class ClientEvent extends Event
{
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getClient()
    {
        return $this->client;
    }
}
