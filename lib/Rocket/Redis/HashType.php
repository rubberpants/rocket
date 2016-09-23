<?php

namespace Rocket\Redis;

class HashType extends BaseType implements \ArrayAccess
{
    protected $hash = [];

    public function clearCache()
    {
        $this->hash = [];
    }

    public function fieldExists($name)
    {
        if (array_key_exists($name, $this->hash)) {
            return true;
        }

        return $this->request(function ($client) use ($name) {
            return $client->hexists($this->getKey(), $name) ? true : false;
        });
    }

    public function getField($name, $forceLookup = false)
    {
        if (!array_key_exists($name, $this->hash) || $forceLookup) {
            $this->hash[$name] = $this->request(function ($client) use ($name) {
                return $client->hget($this->getKey(), $name);
            });
        }

        return $this->hash[$name];
    }

    public function getFields()
    {
        $this->hash = $this->request(function ($client) {
            return $client->hgetall($this->getKey());
        });

        return $this->hash;
    }

    public function incField($name, $by = 1)
    {
        $this->request(function ($client) use ($name, $by) {
            $client->hincrby($this->getKey(), $name, $by);
        });

        if (!array_key_exists($name, $this->hash)) {
            $this->hash[$name] = 1;
        } else {
            $this->hash[$name] += $by;
        }

        return $this;
    }

    public function setField($name, $value)
    {
        $this->request(function ($client) use ($name, $value) {
            $client->hset($this->getKey(), $name, $value);
        });

        $this->hash[$name] = $value;

        return $this;
    }

    public function deleteField($name)
    {
        unset($this->hash[$name]);

        $this->request(function ($client) use ($name) {
            $client->hdel($this->getKey(), $name);
        });

        return $this;
    }

    public function offsetExists($offset)
    {
        return $this->fieldExists($offset);
    }

    public function offsetGet($offset)
    {
        return $this->getField($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->setField($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->deleteField($offset);
    }
}
