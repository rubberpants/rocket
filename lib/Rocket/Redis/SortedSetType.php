<?php

namespace Rocket\Redis;

class SortedSetType extends BaseType
{
    public function addItem($score, $item)
    {
        return $this->request(function ($client) use ($score, $item) {
            return $client->zadd($this->getKey(), $score, $item);
        });
    }

    public function getItems($minScore, $maxScore, $limit)
    {
        return (array) $this->request(function ($client) use ($minScore, $maxScore, $limit) {
            return $client->zrangebyscore($this->getKey(), $minScore, $maxScore, 'LIMIT', 0, $limit);
        });
    }

    public function deleteItem($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->zrem($this->getKey(), $item);
        });
    }

    public function getItemScore($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->zscore($this->getKey(), $item);
        });
    }

    public function getCount()
    {
        return $this->request(function ($client) {
            return $client->zcard($this->getKey());
        });
    }
}
