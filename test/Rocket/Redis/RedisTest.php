<?php

namespace Rocket\Redis;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class RedisTest extends BaseTest
{
    public function testGetClient()
    {
        $harness = Harness::getInstance();
        $redis = $harness->getRedis();
        $this->assertTrue($redis->getClient() instanceof \Rocket\Redis\Client);
        $this->assertNotEmpty($redis->getStatus());
    }

    public function testRequest()
    {
        $harness = Harness::getInstance();
        $redis = $harness->getRedis();

        $testName = __METHOD__;
        $testValue = "Big McLarge Huge";

        $redis->request(function ($client) use ($testName, $testValue) {
            $client->set($testName, $testValue);
        });

        $this->assertEquals($testValue, $redis->request(function ($client) use ($testName) {
            return $client->get($testName);
        }));

        $redis->request(function ($client) use ($testName) {
            $client->del($testName);
        });
    }

    public function testGetStringType()
    {
        $this->assertTrue(Harness::getInstance()->getRedis()->getStringType(1, 'Bulk Vanderhuge') instanceof \Rocket\Redis\StringType);
    }

    public function testGetListType()
    {
        $this->assertTrue(Harness::getInstance()->getRedis()->getListType(1, 'Fridge LargeMeat') instanceof \Rocket\Redis\ListType);
    }

    public function testGetSetType()
    {
        $this->assertTrue(Harness::getInstance()->getRedis()->getSetType(1, 'Butch DeadLift') instanceof \Rocket\Redis\SetType);
    }

    public function testGetHashType()
    {
        $this->assertTrue(Harness::getInstance()->getRedis()->getHashType(1, 'Blast HardCheese') instanceof \Rocket\Redis\HashType);
    }

    public function testPipeline()
    {
        $harness = Harness::getInstance();
        $redis = $harness->getRedis();

        $redis->openPipeline();

        $testName = __METHOD__;
        $testValue = "Reef BlastBody";

        $redis->request(function ($client) use ($testName, $testValue) {
            $client->set($testName, $testValue);
        });

        $redis->closePipeline();

        $this->assertEquals($testValue, $redis->request(function ($client) use ($testName) {
            return $client->get($testName);
        }));

        $redis->request(function ($client) use ($testName) {
            $client->del($testName);
        });
    }
}
