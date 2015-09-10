<?php

namespace Rocket\Plugin\Pump;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;
use Rocket\Job\Job;
use Rocket\Job\JobEvent;
use Rocket\Queue\Queue;
use Rocket\Queue\QueueEvent;

class PumpPluginTest extends BaseTest
{
    protected $plugin;

    public function getPlugin()
    {
        return Harness::getInstance()->getPlugin('pump');
    }

    public function testPumpReadyQueue()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $plugin = $this->getPlugin();
        $plugin->getReadyQueueList()->delete();
        $plugin->getReadyJobList('test')->delete();

        $queue->queueJob('Terror From the Year 5000', 'test');

        list($jobId) = $plugin->pumpReadyQueue(1, 1);

        $job = $queue->getJob($jobId);

        $this->assertEquals(Job::STATUS_DELIVERED, $job->getStatus());
        $this->assertEquals('test', $job->getType());
        $this->assertTrue($job->getDeliverTime() instanceof \DateTime);
        $this->assertFalse($queue->getWaitingSet()->hasItem($jobId));
        $this->assertTrue($queue->getRunningSet()->hasItem($jobId));
        $this->assertNull($queue->getWaitingList()->popItem());
    }

    public function testQueueScheduledJobs()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $plugin = $this->getPlugin();
        $plugin->getScheduledSortedSet()->delete();
        $plugin->getReadyJobList('test')->delete();

        $queue->scheduleJob(new \DateTime(), 'Terror From the Year 5000', 'test');

        list($jobId) = $plugin->queueScheduledJobs(1);

        $job = $queue->getJob($jobId);

        $this->assertEquals(Job::STATUS_WAITING, $job->getStatus());
        $this->assertEquals('test', $job->getType());
        $this->assertTrue($job->getQueueTime() instanceof \DateTime);
        $this->assertTrue($queue->getWaitingSet()->hasItem($jobId));
        $this->assertFalse($queue->getRunningSet()->hasItem($jobId));
        $this->assertNotNull($queue->getWaitingList()->popItem());
    }

    public function testEvents()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->getPlugin()->getReadyQueueList()->delete();
        $this->getPlugin()->getReadyJobList('test')->delete();

        $job = $queue->getJob('Deadliest Prey');

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_QUEUE, new JobEvent($job));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_REQUEUE, new JobEvent($job));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_MOVE, new JobEvent($job));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_UNPARK, new JobEvent($job));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_COMPLETE, new JobEvent($job));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_FAIL, new JobEvent($job));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());

        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_UPDATE, new QueueEvent($queue));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());

        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_RESUME, new QueueEvent($queue));
        $this->assertEquals($queue->getQueueName(), $this->getPlugin()->getReadyQueueList()->popItem());
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertNull($this->getPlugin()->getReadyQueueList()->popItem());
    }
}
