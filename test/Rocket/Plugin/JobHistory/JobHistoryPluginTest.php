<?php

namespace Rocket\Plugin\JobHistory;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;
use Rocket\Job\Job;
use Rocket\Queue\Queue;

class JobHistoryPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        if (is_null($this->plugin)) {
            $this->plugin = new JobHistoryPlugin(Harness::getInstance());
            $this->plugin->setEventDispatcher(Harness::getInstance()->getEventDispatcher());
            $this->plugin->setConfig(Harness::getInstance()->getConfig());
            $this->plugin->setRedis(Harness::getInstance()->getRedis());
            $this->plugin->setLogger(Harness::getInstance()->getLogger());
            $this->plugin->register();
        }

        return $this->plugin;
    }

    public function testJobSchedule()
    {
        $plugin = $this->getPlugin();
        $queue = Harness::getInstance()->getNewQueue();
        $time = new \DateTime();
        $job = $queue->scheduleJob($time, 'King Smilesalot');
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) use ($time) {
            return $value->getEventName() == Job::EVENT_SCHEDULE && $value->getTimestamp() == $time;
        });
    }

    public function testJobQueue()
    {
        $plugin = $this->getPlugin();
        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Prince Ticklepants');
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) use ($queue) {
            return $value->getEventName() == Job::EVENT_QUEUE && $value->getDetails() == $queue->getQueueName();
        });
    }

    public function testJobMove()
    {
        $plugin = $this->getPlugin();
        $queue = Harness::getInstance()->getNewQueue();
        $queue2 =  Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Baron Huggy Von Snugglestein');
        $queue2->moveJob($job);
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) use ($queue2) {
            return $value->getEventName() == Job::EVENT_MOVE && $value->getDetails() == $queue2->getQueueName();
        });
    }

    public function testJobPark()
    {
        $plugin = $this->getPlugin();
        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Queen Happymantime');
        $job->park();
        $job->unpark();
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) {
            return $value->getEventName() == Job::EVENT_PARK;
        });
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) {
            return $value->getEventName() == Job::EVENT_UNPARK;
        });
    }

    public function testJobCancel()
    {
        $plugin = $this->getPlugin();
        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Baroness Fluteflouter');
        $job->cancel();
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) {
            return $value->getEventName() == Job::EVENT_CANCEL;
        });
    }

    public function testJobComplete()
    {
        $plugin = $this->getPlugin();
        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Judge Cackleslacker');
        $job->deliver();
        $job->start('Inspector Flagellate', 10);
        $job->complete(10);
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) {
           return $value->getEventName() == Job::EVENT_START && $value->getDetails() == 'Inspector Flagellate';
        });
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) {
            return $value->getEventName() == Job::EVENT_COMPLETE;
        });
    }

    public function testJobFailed()
    {
        $plugin = $this->getPlugin();
        $queue = Harness::getInstance()->getNewQueue();
        $job = $queue->queueJob('Constable Horswallow');
        $job->deliver();
        $job->start('Jack Corner', 10);
        $job->fail(10, 'Bad luck');
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) {
           return $value->getEventName() == Job::EVENT_START && $value->getDetails() == 'Jack Corner';
        });
        $this->assertArrayTrue($job->getHistory(), function ($key, $value) {
            return $value->getEventName() == Job::EVENT_FAIL;
        });
    }
}
