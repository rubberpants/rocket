<?php

namespace Rocket\Redis;

class StringType extends BaseType
{
    protected $value;

    public function get($forceLookup = false)
    {
        if (is_null($this->value) || $forceLookup) {
            $this->value = $this->request(function ($client) {
                return $client->get($this->getKey());
            });
        }

        return $this->value;
    }

    public function set($value, $ttl = 0)
    {
        $this->request(function ($client) use ($value, $ttl) {
            $client->set($this->getKey(), $value);
            if ($ttl) {
                $client->expire($this->getKey(), $ttl);
            }
        });

        $this->value = $value;

        return $this;
    }

    public function on($ttl = 0)
    {
        return $this->set('ON', $ttl);
    }

    public function off()
    {
        return $this->delete();
    }

    public function isOn()
    {
        return $this->exists();
    }

    public function __toString()
    {
        return $this->get();
    }
}
