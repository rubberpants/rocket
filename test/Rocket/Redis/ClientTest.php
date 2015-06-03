<?php

namespace Rocket\Redis;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class ClientTest extends BaseTest
{
    public function testReconnectIfNeeded()
    {
        $harness = Harness::getInstance();
        $redis = $harness->getRedis();
        $client = $redis->getClient();

        $client->disconnect();

        $client->reconnectIfNeeded();

        $this->assertTrue($client->isConnected());
    }
}
