<?php

namespace Rocket\Queue;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;
use Rocket\Job\Job;

class QueueTest extends BaseTest
{
    public function testInit()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Queue::EVENT_INIT);

        $queue->init();

        $this->assertTrue($queue->getQueuesSet()->hasItem($queue->getQueueName()));
        $this->assertEquals(Harness::getInstance()->getConfig()->get('queues.default_min_running_limit'), $queue->getMinRunningLimit());
        $this->assertEquals(Harness::getInstance()->getConfig()->get('queues.default_max_running_limit'), $queue->getMaxRunningLimit());
        $this->assertEventFired(Queue::EVENT_INIT);
        $this->assertEquals([], $queue->getAllJobs());
        $this->assertEquals(0, $queue->getAllJobCount());
    }

    public function testGetRunningLimit()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->assertEquals(Harness::getInstance()->getConfig()->get('queues.default_min_running_limit'), $queue->getMinRunningLimit());
        $this->assertEquals(Harness::getInstance()->getConfig()->get('queues.default_max_running_limit'), $queue->getMaxRunningLimit());
        $this->assertEquals(2, Harness::getInstance()->getQueue('test-queue1')->getMinRunningLimit());
        $this->assertEquals(2, Harness::getInstance()->getQueue('test-queue2')->getMinRunningLimit());
        $this->assertEquals(4, Harness::getInstance()->getQueue('test-queue1')->getMaxRunningLimit());
        $this->assertEquals(6, Harness::getInstance()->getQueue('test-queue2')->getMaxRunningLimit());
    }

    public function testGetWaitingLimit()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->assertEquals(Harness::getInstance()->getConfig()->get('queues.default_waiting_limit'), $queue->getWaitingLimit());
        $this->assertEquals(400, Harness::getInstance()->getQueue('test-queue3')->getWaitingLimit());
        $this->assertEquals(1, Harness::getInstance()->getQueue('test-queue4')->getWaitingLimit());
    }

    public function testPause()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Queue::EVENT_PAUSE);
        $this->monitorEvent(Queue::EVENT_RESUME);

        $this->assertFalse($queue->isPaused());
        $this->assertFalse($queue->getPausedString()->exists());

        $queue->pause();

        $this->assertTrue($queue->isPaused());
        $this->assertTrue($queue->getPausedString()->exists());
        $this->assertEventFired(Queue::EVENT_PAUSE);

        $queue->resume();

        $this->assertFalse($queue->isPaused());
        $this->assertFalse($queue->getPausedString()->exists());
        $this->assertEventFired(Queue::EVENT_RESUME);
    }

    public function testDisable()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Queue::EVENT_DISABLE);
        $this->monitorEvent(Queue::EVENT_ENABLE);

        $this->assertFalse($queue->isDisabled());
        $this->assertFalse($queue->getDisabledString()->exists());

        $queue->disable();

        $this->assertTrue($queue->isDisabled());
        $this->assertTrue($queue->getDisabledString()->exists());
        $this->assertEventFired(Queue::EVENT_DISABLE);

        $queue->enable();

        $this->assertFalse($queue->isDisabled());
        $this->assertFalse($queue->getDisabledString()->exists());
        $this->assertEventFired(Queue::EVENT_ENABLE);
    }

    public function testSchedule()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_SCHEDULE);
        $this->monitorEvent(Job::EVENT_QUEUE);

        $job = $queue->scheduleJob(new \DateTime(), 'Bold Bigflank');

        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_SCHEDULE_TIME));
        $this->assertEquals(Job::STATUS_SCHEDULED, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_QUEUE_NAME));
        $this->assertTrue($queue->getScheduledSet()->hasItem($job->getId()));
        $this->assertEquals(Job::STATUS_SCHEDULED, $job->getStatus());
        $this->assertTrue($job->getScheduledTime() instanceof \DateTime);
        $this->assertEquals($queue->getQueueName(), $job->getQueueName());
        $this->assertEquals($queue, $job->getQueue());
        $this->assertEquals('Bold Bigflank', $job->getJob());
        $this->assertEventFired(Job::EVENT_SCHEDULE);
        $this->assertEquals([$job->getId()], $queue->getScheduledJobs());
        $this->assertEquals(1, $queue->getScheduledJobCount());

        $plugin = Harness::getInstance()->getPlugin('pump');
        $plugin->execute(0, 0, 10);

        $job->getHash()->clearCache();

        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_QUEUE_TIME));
        $this->assertEquals(Job::STATUS_WAITING, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_QUEUE_NAME));
        $this->assertEquals($job->getId(), $queue->getWaitingList()->getItem(0));
        $this->assertEquals(Job::STATUS_WAITING, $job->getStatus());
        $this->assertTrue($job->getQueueTime() instanceof \DateTime);
        $this->assertEquals($queue->getQueueName(), $job->getQueueName());
        $this->assertEquals($queue, $job->getQueue());
        $this->assertEquals('Bold Bigflank', $job->getJob());
        $this->assertEventFired(Job::EVENT_QUEUE);
        $this->assertEquals([$job->getId()], $queue->getWaitingJobs());
        $this->assertEquals(1, $queue->getWaitingJobCount());
        $this->assertEquals([$job->getId()], $queue->getWaitingJobsByPage(1, 1));
    }

    public function testQueue()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_QUEUE);

        $job = $queue->queueJob('Trunk SlamChest');

        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_QUEUE_TIME));
        $this->assertEquals(Job::STATUS_WAITING, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_QUEUE_NAME));
        $this->assertEquals($job->getId(), $queue->getWaitingList()->getItem(0));
        $this->assertEquals(Job::STATUS_WAITING, $job->getStatus());
        $this->assertTrue($job->getQueueTime() instanceof \DateTime);
        $this->assertEquals($queue->getQueueName(), $job->getQueueName());
        $this->assertEquals($queue, $job->getQueue());
        $this->assertEquals('Trunk SlamChest', $job->getJob());
        $this->assertEventFired(Job::EVENT_QUEUE);
        $this->assertEquals([$job->getId()], $queue->getWaitingJobs());
        $this->assertEquals(1, $queue->getWaitingJobCount());
        $this->assertEquals([$job->getId()], $queue->getWaitingJobsByPage(1, 1));
    }

    public function testQueueFull()
    {
        $queue = Harness::getInstance()->getQueue('test-queue4');

        $queue->pause(); //Make sure no jobs get delivered during this test

        $queue->flushJobsByStatus(Job::STATUS_WAITING);

        $this->assertTrue($queue->queueJob('Chain ThickNeck') instanceof Job);

        $this->monitorEvent(Queue::EVENT_FULL);

        $this->assertException(function () use ($queue) { $queue->queueJob('Johnny LongJaw'); }, 'Rocket\RocketException', 'Cannot queue job Johnny Lon... because waiting limit was reached');

        $this->assertEventFired(Queue::EVENT_FULL);
    }

    public function testMove()
    {
        $queue1 = Harness::getInstance()->getNewQueue();
        $queue2 = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_MOVE);

        $job = $queue1->queueJob('Chaz KnobJunk');

        $queue2->moveJob($job);

        $this->assertEventFired(Job::EVENT_MOVE);
        $this->assertEquals($queue2->getQueueName(), $job->getQueueName());
    }

    public function testDelete()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Queue::EVENT_DELETE);

        $queue->init();

        $queue->delete();

        $this->assertFalse($queue->getQueuesSet()->hasItem($queue->getQueueName()));

        $this->assertEventFired(Queue::EVENT_DELETE);
    }
}
