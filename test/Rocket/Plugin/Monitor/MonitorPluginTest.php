<?php

namespace Rocket\Plugin\Monitor;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;
use Rocket\Job\Job;
use Rocket\Queue\Queue;

class MonitorPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        return Harness::getInstance()->getPlugin('monitor');
    }

    public function testExecute()
    {
        $plugin = $this->getPlugin();

        $plugin->getEventsSortedSet()->delete();

        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Gypsy');
        sleep(3);
        $plugin->execute(10);
        $job->getHash()->clearCache();
        $this->assertTrue($job->isAlerting());

        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Cambot');
        $job->deliver();
        sleep(3);
        $plugin->execute(10);
        $job->getHash()->clearCache();
        $this->assertTrue($job->isAlerting());

        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Ortega');
        $job->deliver();
        $job->start('Brack', 10);
        sleep(3);
        $plugin->execute(10);
        $job->getHash()->clearCache();
        $this->assertTrue($job->isAlerting());

        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Tom Servo');
        $job->deliver();
        $job->start('Brack', 10);
        $job->complete(10);
        sleep(3);
        $plugin->execute(10);
        $job->getHash()->clearCache();
        $this->assertFalse($job->getHash()->exists());

        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Crow');
        $job->deliver();
        $job->start('Ruth', 10);
        $job->fail(10, 'Because reasons');
        sleep(3);
        $plugin->execute(10);
        $job->getHash()->clearCache();
        $this->assertFalse($job->getHash()->exists());

        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Professor Bobo');
        $job->cancel();
        sleep(3);
        $plugin->execute(10);
        $job->getHash()->clearCache();
        $this->assertFalse($job->getHash()->exists());
    }
}
