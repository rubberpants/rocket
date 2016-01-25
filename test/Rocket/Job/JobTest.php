<?php

namespace Rocket\Job;

use Rocket\RocketException;
use Rocket\Test\BaseTest;
use Rocket\Test\Harness;
use Rocket\Queue\Queue;

class JobTest extends BaseTest
{
    public function testShift()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_SHIFT);

        $job1 = $queue->queueJob('Smash LampJaw');
        $job2 = $queue->queueJob('Dirk HardPec');

        $this->assertEquals([$job1->getId(), $job2->getId()], $queue->getWaitingList()->getItems(1, 10));

        $job2->shiftBefore($job1->getId());

        $this->assertEquals([$job2->getId(), $job1->getId()], $queue->getWaitingList()->getItems(1, 10));
        $this->assertEventFired(Job::EVENT_SHIFT, false);

        $job2->shiftAfter($job1->getId());

        $this->assertEquals([$job1->getId(), $job2->getId()], $queue->getWaitingList()->getItems(1, 10));
        $this->assertEventFired(Job::EVENT_SHIFT);

        $this->assertEquals(2, $queue->getWaitingJobCount());
    }

    public function testPark()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $job = $queue->queueJob('Thick McRunfast');

        $this->monitorEvent(Job::EVENT_PARK);

        $this->assertTrue($job->park());

        $this->assertEquals(Job::STATUS_PARKED, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertEventFired(Job::EVENT_PARK);
        $this->assertEquals(Job::STATUS_PARKED, $job->getStatus());

        $this->assertException(function () use ($job) { $job->park(); }, 'Rocket\RocketException', 'Job park failed');

        $this->assertEquals([$job->getId()], $queue->getParkedJobs());
        $this->assertEquals(1, $queue->getParkedJobCount());
        $this->assertNull($queue->getWaitingList()->getItem($job->getId()));

        $this->monitorEvent(Job::EVENT_UNPARK);

        $this->assertTrue($job->unpark());

        $this->assertEquals(Job::STATUS_WAITING, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertEquals($job->getId(), $queue->getWaitingList()->getItem(0));
        $this->assertEventFired(Job::EVENT_UNPARK);
        $this->assertEquals(Job::STATUS_WAITING, $job->getStatus());

        $this->assertException(function () use ($job) { $job->unpark(); }, 'Rocket\RocketException', 'Job unpark failed');
    }

    public function testCancel()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $job = $queue->queueJob('Bob Johnson');

        $this->monitorEvent(Job::EVENT_CANCEL);

        $this->assertTrue($job->cancel());

        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_CANCEL_TIME));
        $this->assertEquals(Job::STATUS_CANCELLED, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertEventFired(Job::EVENT_CANCEL);
        $this->assertEquals(Job::STATUS_CANCELLED, $job->getStatus());
        $this->assertTrue($job->getCancelTime() instanceof \DateTime);

        $this->assertException(function () use ($job) { $job->cancel(); }, 'Rocket\RocketException', 'Cannot cancel job because it is not scheduled, waiting, or parked');

        $this->assertEquals([$job->getId()], $queue->getCancelledJobs());
        $this->assertEquals(1, $queue->getCancelledJobCount());

        $this->monitorEvent(Job::EVENT_DELETE);

        $this->assertTrue($job->delete());
        $this->assertFalse($job->getHash()->exists());
        $this->assertEventFired(Job::EVENT_DELETE);
    }

    public function testDeliver()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_DELIVER);

        $job = $queue->queueJob('Professor Spooner');

        $this->assertTrue($job->deliver());

        $this->assertEquals(Job::STATUS_DELIVERED, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertEventFired(Job::EVENT_DELIVER);
        $this->assertEquals(Job::STATUS_DELIVERED, $job->getStatus());

        $this->assertException(function () use ($job) { $job->deliver(); }, 'Rocket\RocketException', 'Job delivery failed');
    }

    public function testStart()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_START);

        $job = $queue->queueJob('Top BeefKnob');

        $job->deliver('Lea');

        $this->assertTrue($job->start('Commander Kalgan', 10));

        $this->assertEquals(Job::STATUS_RUNNING, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertEventFired(Job::EVENT_START);
        $this->assertEquals(Job::STATUS_RUNNING, $job->getStatus());
        $this->assertEquals('Commander Kalgan', $job->getHash()->getField(Job::FIELD_WORKER_NAME));
        $this->assertEquals('Commander Kalgan', $job->getWorkerName());
        $this->assertTrue($job->getStartTime() instanceof \DateTime);
        $this->assertTrue($job->getQueue()->getRunningSet()->hasItem($job->getId()));
        $this->assertEquals([$job->getId()], $queue->getRunningJobs());
        $this->assertEquals(1, $queue->getRunningJobCount());
        $this->assertEquals(1, $job->getAttempts());

        $this->monitorEvent(Job::EVENT_PROGRESS);

        $job->progress(50);

        $this->assertEquals(50, $job->getProgress());
        $this->assertEventFired(Job::EVENT_PROGRESS);
    }

    public function testComplete()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_COMPLETE);

        $job = $queue->queueJob('Buff DrinkLots');

        $job->deliver('Lea');

        $job->start('Biff Fizzlebeef', 10);

        $job->complete(10);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertEventFired(Job::EVENT_COMPLETE);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
        $this->assertTrue($job->getCompleteTime() instanceof \DateTime);

        $this->assertException(function () use ($job) { $job->complete(10); }, 'Rocket\RocketException', 'Could not complete job because it is not running');

        $this->assertEquals([$job->getId()], $queue->getCompletedJobs());
        $this->assertEquals(1, $queue->getCompletedJobCount());
    }

    public function testFail()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $this->monitorEvent(Job::EVENT_FAIL);

        $job = $queue->queueJob('Flint IronStag');

        $job->deliver('Lea');

        $job->start('Biff Fizzlebeef', 10);

        $job->fail(10, 'If only you had worked a little harder');

        $this->assertEquals(Job::STATUS_FAILED, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertEventFired(Job::EVENT_FAIL);
        $this->assertEquals(Job::STATUS_FAILED, $job->getStatus());
        $this->assertTrue($job->getFailTime() instanceof \DateTime);

        $this->assertException(function () use ($job) { $job->fail(10, ''); }, 'Rocket\RocketException', 'Could not fail job because it is not running or paused');

        $this->assertEquals([$job->getId()], $queue->getFailedJobs());
        $this->assertEquals(1, $queue->getFailedJobCount());
        $this->assertEquals('If only you had worked a little harder', $job->getFailureMessage());
    }

    public function testRequeue()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $job = $queue->queueJob('Railing Kill');

        $this->monitorEvent(Job::EVENT_REQUEUE);

        $job->cancel();

        $this->assertTrue($job->requeue());

        $this->assertEquals(Job::STATUS_WAITING, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_QUEUE_NAME));
        $this->assertEquals($job->getId(), $queue->getWaitingList()->getItem(0));
        $this->assertEquals(Job::STATUS_WAITING, $job->getStatus());
        $this->assertEventFired(Job::EVENT_REQUEUE);
    }

    public function testRequeueSchedule()
    {
        $queue = Harness::getInstance()->getNewQueue();

        $job = $queue->queueJob('Crash Bigbad');

        $this->monitorEvent(Job::EVENT_REQUEUE);

        $job->cancel();

        $this->assertTrue($job->requeue(new \DateTime()));

        $this->assertEquals(Job::STATUS_SCHEDULED, $job->getHash()->getField(Job::FIELD_STATUS));
        $this->assertTrue($job->getHash()->fieldExists(Job::FIELD_QUEUE_NAME));
        $this->assertEquals(true, $queue->getScheduledSet()->hasItem($job->getId()));
        $this->assertEquals(Job::STATUS_SCHEDULED, $job->getStatus());
        $this->assertEventFired(Job::EVENT_REQUEUE);
    }
}
