<?php

namespace Rocket\Redis;

interface RedisInterface
{
    public function getClient();
    public function openPipeline();
    public function closePipeline($timeout = 0);
    public function request(\Closure $function);
    public function promoteToMaster();
    public function shutdown();
    public function getStatus();
    public function isRunning();
}
