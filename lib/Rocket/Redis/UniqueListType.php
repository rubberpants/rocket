<?php

namespace Rocket\Redis;

class UniqueListType extends BaseType
{
    public function getSetKey()
    {
        return $this->getKey()."_SET";
    }

    public function pushItem($item)
    {
        return $this->request(function ($client) use ($item) {
            if (!$client->sismember($this->getSetKey(), $item)) {
                $client->sadd($this->getSetKey(), $item);

                return $client->rpush($this->getKey(), $item);
            }
        });
    }

    public function popItem()
    {
        return $this->request(function ($client) {
            if ($item = $client->lpop($this->getKey())) {
                $client->srem($this->getSetKey(), $item);

                return $item;
            }
        });
    }

    public function blockAndPopItem($timeout = 0)
    {
        return $this->request(function ($client) use ($timeout) {
            list($list, $item) = $client->blpop($this->getKey(), $timeout);
            if ($item) {
                $client->srem($this->getSetKey(), $item);
            }

            return $item;
        });
    }

    public function hasItem($item)
    {
        return $this->request(function ($client) use ($item) {
            return $client->sismember($this->getSetKey(), $item) ? true : false;
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
            $client->srem($this->getSetKey(), $item);

            return $client->lrem($this->getKey(), 0, $item);
        });
    }

    public function insertItem($item, $position, $pivot)
    {
        return $this->request(function ($client) use ($item, $pivot, $position) {
            if (!$client->sismember($this->getSetKey(), $item)) {
                $client->sadd($this->getSetKey(), $item);

                return $client->linsert($this->getKey(), $position, $pivot, $item);
            }
        });
    }

    public function delete()
    {
        return $this->request(function () {
            $this->getClient()->del($this->getSetKey());

            return $this->getClient()->del($this->getKey()) ? true : false;
        });
    }

    public function getCount()
    {
        return $this->request(function ($client) {
            return $client->scard($this->getSetKey());
        });
    }
}
