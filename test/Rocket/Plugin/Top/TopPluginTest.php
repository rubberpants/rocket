<?php

namespace Rocket\Plugin\Top;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;

class TopPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        if (is_null($this->plugin)) {
            $this->plugin = new TopPlugin(Harness::getInstance());
            $this->plugin->setEventDispatcher(Harness::getInstance()->getEventDispatcher());
            $this->plugin->setConfig(Harness::getInstance()->getConfig());
            $this->plugin->setRedis(Harness::getInstance()->getRedis());
            $this->plugin->setLogger(Harness::getInstance()->getLogger());
            $this->plugin->register();
        }

        return $this->plugin;
    }

    public function testTop()
    {
        parent::setup();

        $plugin = Harness::getInstance()->getPlugin('aggregate');

        $plugin->getAllWaitingJobsSet()->delete();
        $plugin->getAllRunningJobsSet()->delete();

        $runningQueue = Harness::getInstance()->getNewQueue();
        $runningJob = $runningQueue->queueJob('Jackmerius Tacktheritrix');
        $runningJob->deliver('Quatro Quatro');
        $runningJob->start('Quatro Quatro', 10);

        sleep(1);

        $waitingQueue = Harness::getInstance()->getNewQueue();
        $waitingJob = $waitingQueue->queueJob('Javarus Jamar Javarison-Lamar');

        sleep(1);

        Harness::getInstance()->setQueues([$runningQueue->getQueueName(), $waitingQueue->getQueueName()]);

        $topQueues = $this->getPlugin()->getTopQueues(TopPlugin::METRIC_WAITING, 6);
        $this->assertTrue($this->getPlugin()->getTopCacheString('TOP:QUEUES:waiting:6')->exists());
        $this->assertEquals(2, count($topQueues));
        $this->assertEquals([$waitingQueue->getQueueName(), $runningQueue->getQueueName()], array_keys($topQueues));

        $topQueues = $this->getPlugin()->getTopQueues(TopPlugin::METRIC_RUNNING, 6);
        $this->assertTrue($this->getPlugin()->getTopCacheString('TOP:QUEUES:running:6')->exists());
        $this->assertEquals(2, count($topQueues));
        $this->assertEquals([$runningQueue->getQueueName(), $waitingQueue->getQueueName()], array_keys($topQueues));

        $topJobs = $this->getPlugin()->getTopJobs(TopPlugin::METRIC_WAITING, 6);
        $this->assertTrue($this->getPlugin()->getTopCacheString('TOP:JOBS:waiting:6')->exists());
        $this->assertEquals(1, count($topJobs));
        $this->assertEquals([$waitingJob->getId()], array_keys($topJobs));

        $topJobs = $this->getPlugin()->getTopJobs(TopPlugin::METRIC_RUNNING, 6);
        $this->assertTrue($this->getPlugin()->getTopCacheString('TOP:JOBS:running:6')->exists());
        $this->assertEquals(1, count($topJobs));
        $this->assertEquals([$runningJob->getId()], array_keys($topJobs));
    }
}
