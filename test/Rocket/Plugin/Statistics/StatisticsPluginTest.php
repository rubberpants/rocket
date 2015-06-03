<?php

namespace Rocket\Plugin\Statistics;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class StatisticsPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        if (is_null($this->plugin)) {
            $this->plugin = new StatisticsPlugin(Harness::getInstance());
            $this->plugin->setEventDispatcher(Harness::getInstance()->getEventDispatcher());
            $this->plugin->setConfig(Harness::getInstance()->getConfig());
            $this->plugin->setRedis(Harness::getInstance()->getRedis());
            $this->plugin->setLogger(Harness::getInstance()->getLogger());
        }

        return $this->plugin;
    }

    public function testStats()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->getPlugin()->register();

        $job = $queue->queueJob('Donkey Water');
        $job->cancel();

        $job = $queue->queueJob('Donkey Water 2 - The Sequel');
        $job->deliver();
        $job->start('Rowzdower', 10);
        $job->complete(10);

        $job = $queue->queueJob('Donkey Water 3 - Again?');
        $job->deliver();
        $job->start('Rowzdower', 10);
        $job->fail(10, 'Thanks Obama');
        $job->delete();

        $allStats = $this->getPlugin()->getAllStatistics();

        $this->assertEquals(12, count($allStats));

        $stats = array_shift($allStats);

        $this->assertTrue($stats['queued'] > 0);
        $this->assertTrue($stats['cancelled'] > 0);
        $this->assertTrue($stats['delivered'] > 0);
        $this->assertTrue($stats['started'] > 0);
        $this->assertTrue($stats['completed'] > 0);
        $this->assertTrue($stats['failed'] > 0);
        $this->assertTrue($stats['deleted'] > 0);

        $queueStats = $this->getPlugin()->getQueueStatistics($queue);

        $this->assertEquals(12, count($queueStats));
        $this->assertEquals([
            'queued' => '3',
            'cancelled' => '1',
            'delivered' => '2',
            'started' => '2',
            'completed' => '1',
            'failed' => '1',
            'deleted' => '1',
        ], array_shift($queueStats));
    }
}
