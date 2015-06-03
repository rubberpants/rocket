<?php

namespace Rocket\Plugin\Router;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class RouterPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        if (is_null($this->plugin)) {
            $this->plugin = new RouterPlugin(Harness::getInstance());
            $this->plugin->setEventDispatcher(Harness::getInstance()->getEventDispatcher());
            $this->plugin->setConfig(Harness::getInstance()->getConfig());
            $this->plugin->setRedis(Harness::getInstance()->getRedis());
            $this->plugin->setLogger(Harness::getInstance()->getLogger());
        }

        return $this->plugin;
    }

    public function testConfig()
    {
        $this->getPlugin()->register();

        $rules = $this->getPlugin()->getRules();

        $this->assertEquals('.dest', $this->getPlugin()->getDefaultRule()->getQueueNameExpr());
        $this->assertEquals('.estimate > 10', $rules[0]->getFilterExpr());
        $this->assertEquals('.dest+"-large"', $rules[0]->getQueueNameExpr());

        $this->assertEquals('if .estimate > 10 then .dest+"-large" else .dest end', $this->getPlugin()->getRoutingFilter());
    }

    public function testRouteJob()
    {
        $this->getPlugin()->register();

        $job = $this->getPlugin()->routeJob(json_encode(['dest' => 'the fires of ishtar']));
        $this->assertEquals('the fires of ishtar', $job->getQueueName());

        $job = $this->getPlugin()->routeJob(json_encode(['dest' => 'the fires of ishtar', 'estimate' => 20]));
        $this->assertEquals('the fires of ishtar-large', $job->getQueueName());
    }

    /**
     * @dataProvider provideJq
     */
    public function testJq($input, $filter, $expectedOutput)
    {
        $this->assertEquals($expectedOutput, $this->getPlugin()->executeJq($filter, json_encode($input)));
    }

    public function provideJq()
    {
        return [
            [[], '.dest', null],
            [['dest' => 'test'], '.dest', "test"],
            [['dest' => 'test'], '.dest+"-large"', "test-large"],
            [['dest' => 'test', 'estimate' => 14], 'if .estimate > 10 then .dest+"-large" else .dest end', "test-large"],
            [['dest' => 'test', 'estimate' => 9], 'if .estimate > 10 then .dest+"-large" else .dest end', "test"],
            [['dest' => 'test'], 'if .estimate > 10 then .dest+"-large" else .dest end', "test"],
        ];
    }
}
