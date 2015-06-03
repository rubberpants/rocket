<?php

namespace Rocket\Config;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class ConfigTest extends BaseTest
{
    public function testConfig()
    {
        $harness = Harness::getInstance();

        $config = $harness->getConfig();

        $this->assertEquals('test', $config->get('application_name'));
        $this->assertEquals("tcp://127.0.0.1:6379?database=10", $config->getRedisConnections());
    }

    public function testBlankConfig()
    {
        $config = new Config();

        $this->assertEquals('DEFAULT_QUEUE', $config->get('default_queue_name', 'DEFAULT_QUEUE'));
        $this->assertEquals(2, $config->get('default_concurrency_limit', 2));

        try {
            $config->get('application_name');
        } catch (ConfigException $e) {
            $this->assertEquals('Configuration value application_name required', $e->getMessage());
        }

        try {
            $config->get('redis_connections');
        } catch (ConfigException $e) {
            $this->assertEquals('Configuration value redis_connections required', $e->getMessage());
        }
    }
}
