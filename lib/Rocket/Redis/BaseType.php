<?php

namespace Rocket\Redis;

abstract class BaseType
{
    use RedisTrait;

    protected $key;

    public function __construct(RedisInterface $redis, $key)
    {
        $this->redis = $redis;
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getClient()
    {
        return $this->redis->getClient();
    }

    public function exists()
    {
        return $this->request(function () {
            return $this->getClient()->exists($this->getKey()) ? true : false;
        });
    }

    public function delete()
    {
        return $this->request(function () {
            return $this->getClient()->del($this->getKey()) ? true : false;
        });
    }

    public function expireAt($timestamp)
    {
        return $this->request(function () use ($timestamp) {
            return $this->getClient()->expireat($this->getKey(), $timestamp);
        });
    }

    public function expire($seconds)
    {
        return $this->request(function () use ($seconds) {
            return $this->getClient()->expire($this->getKey(), $seconds);
        });
    }

    protected function request(\Closure $function)
    {
        return $this->redis->request($function);
    }
}
