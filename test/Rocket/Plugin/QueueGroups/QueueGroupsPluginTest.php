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
        $this->getPlugin()->getGroupQueuesSet('group2')->delete();

        $queue = Harness::getInstance()->getNewQueue();

        $job1 = $queue->scheduleJob(new \DateTime(), '{"group":"GROUP1"}');
        $job2 = $queue->queueJob('{"group":"group2"}');

        $this->assertContains('group1', $this->getPlugin()->getGroups());
        $this->assertContains('group2', $this->getPlugin()->getGroups());
        $this->assertContains($queue->getQueueName(), $this->getPlugin()->getQueuesByGroup('group1'));
        $this->assertContains($queue->getQueueName(), $this->getPlugin()->getQueuesByGroup('group2'));

        $job1->delete();
        $job2->delete();
        $queue->delete();

        $this->assertEquals([], $this->getPlugin()->getQueuesByGroup('group1'));
        $this->assertEquals([], $this->getPlugin()->getQueuesByGroup('group2'));
        $this->assertEquals([], $this->getPlugin()->getGroups());
    }
}
