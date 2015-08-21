<?php

namespace Rocket\Plugin\UniqueJob;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class UniqueJobPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        if (is_null($this->plugin)) {
            $this->plugin = new UniqueJobPlugin(Harness::getInstance());
            $this->plugin->setEventDispatcher(Harness::getInstance()->getEventDispatcher());
            $this->plugin->setConfig(Harness::getInstance()->getConfig());
            $this->plugin->setRedis(Harness::getInstance()->getRedis());
            $this->plugin->setLogger(Harness::getInstance()->getLogger());
        }

        return $this->plugin;
    }

    public function testUniqueJob()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $plugin = $this->getPlugin();

        $plugin->register();

        $job = $queue->queueJob('Pumaman');

        $this->assertEquals($job->getId(), $plugin->getJobIdIfActive($job));

        $this->assertEquals($job->getId(), $plugin->getJobIdIfActiveByDigest(sha1('Pumaman')));

        $job->cancel();

        $this->assertNull($plugin->getJobIdIfActive($job));
    }
}
