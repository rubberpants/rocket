<?php

namespace Rocket\Plugin\QueueGroups;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class QueueGroupsPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        if (is_null($this->plugin)) {
            $this->plugin = new QueueGroupsPlugin(Harness::getInstance());
            $this->plugin->setEventDispatcher(Harness::getInstance()->getEventDispatcher());
            $this->plugin->setConfig(Harness::getInstance()->getConfig());
            $this->plugin->setRedis(Harness::getInstance()->getRedis());
            $this->plugin->setLogger(Harness::getInstance()->getLogger());
        }

        return $this->plugin;
    }

    public function testGroupQueue()
    {
        $this->getPlugin()->register();

        $this->getPlugin()->getAllGroupsSet()->delete();
        $this->getPlugin()->getGroupQueuesSet('group1')->delete();

        $queue = Harness::getInstance()->getNewQueue();

        $job1 = $queue->scheduleJob(new \DateTime(), '{"group":"GROUP1"}');

        $this->assertContains('group1', $this->getPlugin()->getGroups());
        $this->assertContains($queue->getQueueName(), $this->getPlugin()->getQueuesByGroup('group1'));

        $job1->delete();
        $queue->delete();

        $this->assertEquals([], $this->getPlugin()->getQueuesByGroup('group1'));
    }
}
