<?php

namespace Rocket\Redis;

class ListType extends BaseType
{
    public function pushItem($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->rpush($this->getKey(), $item);
        });
    }

    public function popItem()
    {
        return $this->request(function ($client) {
            return $client->lpop($this->getKey());
        });
    }

    public function blockAndPopItem($timeout = 0)
    {
        return $this->request(function ($client) use ($timeout) {
            list($list, $item) = $client->blpop($this->getKey(), $timeout);

            return $item;
        });
    }

    public function getItem($index)
    {
        return $this->request(function ($client) use ($index) {
            return $client->lindex($this->getKey(), $index);
        });
    }

    public function getItems($page = 1, $pagesize = 100)
    {
        $start = ($page-1)*$pagesize;
        $stop = $start + $pagesize;

        return (array) $this->request(function ($client) use ($start, $stop) {
            return $client->lrange($this->getKey(), $start, $stop);
        });
    }

    public function deleteItem($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->lrem($this->getKey(), 0, $item);
        });
    }

    public function insertItem($item, $position, $pivot)
    {
        return $this->request(function ($client) use ($item, $pivot, $position) {
            return $client->linsert($this->getKey(), $position, $pivot, $item);
        });
    }

    public function getLength()
    {
        return $this->request(function ($client) {
            return $client->llen($this->getKey());
        });
    }

}
