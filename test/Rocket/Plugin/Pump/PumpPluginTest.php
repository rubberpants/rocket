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

    public function testExecute()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $plugin = $this->getPlugin();
        $plugin->getReadyQueueSet()->delete();
        $plugin->getReadyJobList()->delete();

        $queue->queueJob('Terror From the Year 5000');

        list($jobId) = $plugin->execute(1, 1, 0);

        $job = $queue->getJob($jobId);

        $this->assertEquals(Job::STATUS_DELIVERED, $job->getStatus());
        $this->assertTrue($job->getDeliverTime() instanceof \DateTime);
        $this->assertFalse($queue->getWaitingSet()->hasItem($jobId));
        $this->assertTrue($queue->getRunningSet()->hasItem($jobId));
        $this->assertNull($queue->getWaitingList()->getItem(0));
    }

    public function testEvents()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->getPlugin()->getReadyQueueSet()->delete();
        $this->getPlugin()->getReadyJobList()->delete();

        $job = $queue->getJob('Deadliest Prey');

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_QUEUE, new JobEvent($job));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_REQUEUE, new JobEvent($job));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_MOVE, new JobEvent($job));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_UNPARK, new JobEvent($job));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_COMPLETE, new JobEvent($job));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));

        $this->getPlugin()->getEventDispatcher()->dispatch(Job::EVENT_FAIL, new JobEvent($job));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));

        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_UPDATE, new QueueEvent($queue));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));

        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_RESUME, new QueueEvent($queue));
        $this->assertTrue($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
        $this->getPlugin()->getEventDispatcher()->dispatch(Queue::EVENT_DELETE, new QueueEvent($queue));
        $this->assertFalse($this->getPlugin()->getReadyQueueSet()->hasItem($queue->getQueueName()));
    }
}
