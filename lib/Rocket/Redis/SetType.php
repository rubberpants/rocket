<?php

namespace Rocket\Redis;

class SetType extends BaseType
{
    public function hasItem($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->sismember($this->getKey(), $item);
        });
    }

    public function popItem()
    {
        return $this->request(function ($client) {
            return $client->spop($this->getKey());
        });
    }

    public function getItems()
    {
        return (array) $this->request(function ($client) {
            return $client->smembers($this->getKey());
        });
    }

    public function getCount()
    {
        return $this->request(function ($client) {
            return $client->scard($this->getKey());
        });
    }

    public function addItem($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->sadd($this->getKey(), $item);
        });
    }

    public function deleteItem($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->srem($this->getKey(), $item);
        });
    }

    public function moveTo(SetType $targetSet, $item)
    {
        return $this->request(function ($client) use ($targetSet, $item) {
            return $client->smove($this->getKey(), $targetSet->getKey(), $item);
        });
    }

    public function moveFrom(SetType $sourceSet, $item)
    {
        return $this->request(function ($client) use ($sourceSet, $item) {
            return $client->smove($sourceSet->getKey(), $this->getKey(), $item);
        });
    }
}
