<?php

namespace Rocket\Redis;

trait RedisTrait
{
    protected $redis;

    public function setRedis(RedisInterface $redis)
    {
        $this->redis = $redis;

        return $this;
    }

    public function getRedis()
    {
        return $this->redis;
    }
}
