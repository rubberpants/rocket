<?php

namespace Rocket\Worker;

use Rocket\Test\BaseTest;
use Rocket\Test\Harness;
use Rocket\Job\Job;

class WorkerTest extends BaseTest
{
    public function testCommand()
    {
        $worker = Harness::getInstance()->getWorker('Ned the Nanite');

        $worker->setCommand('Fix the ship');

        $recievedCommand = '';

        try {
            $worker->getNewJob();
        } catch (WorkerCommandException $e) {
            $this->assertEquals('Fix the ship', $e->getCommand());
        }
    }

    public function testActivityAndDelete()
    {
        $worker = Harness::getInstance()->getWorker('Peanut');

        $this->monitorEvent(Worker::EVENT_ACTIVITY);
        $worker->getNewJob('default', '', 1.0, 1);
        $this->assertEventFired(Worker::EVENT_ACTIVITY);

        $this->monitorEvent(Worker::EVENT_DELETE);
        $worker->delete();
        $this->assertEventFired(Worker::EVENT_DELETE);
    }

    public function testGetNewJob()
    {
        $worker = Harness::getInstance()->getWorker('Brain Guy');

        $queue = Harness::getInstance()->getNewQueue();

        Harness::getInstance()->getPlugin('pump')->getReadyQueueSet()->delete();
        Harness::getInstance()->getPlugin('pump')->getReadyJobList('test')->delete();
        Harness::getInstance()->getPlugin('monitor')->getEventsSortedSet()->delete();

        $queue->queueJob('The Crawling Eye', 'test');

        $this->monitorEvent(Job::EVENT_DELIVER);

        $this->assertTrue($worker->getNewJob('test', 'Elvis killed JFK'));
        $this->assertEquals(Job::STATUS_DELIVERED, $worker->getCurrentJob()->getStatus());
        $this->assertEquals('Brain Guy', $worker->getCurrentJob()->getWorkerName());
        $this->assertEquals('test', $worker->getCurrentJob()->getType());
        $this->assertEquals($queue->getQueueName(), $worker->getCurrentQueueName());
        $this->assertEquals($worker->getCurrentJob()->getId(), $worker->getCurrentJobId());
        $this->assertEquals(1, $worker->getJobsDelivered());
        $this->assertEquals('Elvis killed JFK', $worker->getInfo());
        $this->assertEventFired(Job::EVENT_DELIVER);
        $this->assertTrue($worker->getCurrentJob()->getQueue()->getRunningSet()->hasItem($worker->getCurrentJob()->getId()));

        $jobId = $worker->getCurrentJob()->getId();
        $this->assertTrue($worker->getNewJob('test', 'Elvis killed JFK'));
        $this->assertEquals($jobId, $worker->getCurrentJob()->getId());

        $this->monitorEvent(Job::EVENT_START);
        $this->monitorEvent(Worker::EVENT_JOB_START);

        $this->assertTrue($worker->startCurrentJob());
        $this->assertEquals(Job::STATUS_RUNNING, $worker->getCurrentJob()->getStatus());
        $this->assertEquals('Brain Guy', $worker->getCurrentJob()->getWorkerName());
        $this->assertEquals(1, $worker->getJobsStarted());
        $this->assertEquals(time(), $worker->getLastJobStart());
        $this->assertEventFired(Job::EVENT_START);
        $this->assertEventFired(Worker::EVENT_JOB_START);

        $this->monitorEvent(Job::EVENT_PROGRESS);
        $worker->progressCurrentJob(1);
        $this->assertEquals(1, $worker->getCurrentJob()->getProgress());
        $this->assertEventFired(Job::EVENT_PROGRESS);

        $this->monitorEvent(Worker::EVENT_JOB_PAUSE);
        $this->assertTrue($worker->pauseCurrentJob());
        $this->assertEquals(Worker::FLAG_PAUSE, $worker->getHash()->getField(Worker::FIELD_FLAG));
        $this->assertEventFired(Worker::EVENT_JOB_PAUSE);

        $this->monitorEvent(Job::EVENT_PAUSE);
        try {
            $worker->progressCurrentJob(2);
        } catch (WorkerPauseException $e) {
            $this->assertTrue($worker->getCurrentJob()->pause());
            $this->assertEventFired(Job::EVENT_PAUSE);
            $this->assertEquals(2, $worker->getCurrentJob()->getProgress());
        }

        $this->monitorEvent(Worker::EVENT_JOB_RESUME);
        $this->assertTrue($worker->resumeCurrentJob());
        $this->assertEquals(Worker::FLAG_RESUME, $worker->getHash()->getField(Worker::FIELD_FLAG));
        $this->assertEventFired(Worker::EVENT_JOB_RESUME);

        $this->monitorEvent(Job::EVENT_RESUME);
        try {
            $worker->progressCurrentJob(2);
        } catch (WorkerResumeException $e) {
            $this->assertTrue($worker->getCurrentJob()->resume());
            $this->assertEventFired(Job::EVENT_RESUME);
            $this->assertEquals(2, $worker->getCurrentJob()->getProgress());
        }

        sleep(1);

        $this->monitorEvent(Job::EVENT_PROGRESS);
        $worker->progressCurrentJob(4);
        $this->assertEquals(4, $worker->getCurrentJob()->getProgress());
        $this->assertEventFired(Job::EVENT_PROGRESS);

        $this->monitorEvent(Worker::EVENT_JOB_DONE);

        $this->assertTrue($worker->completeCurrentJob());
        $this->assertEquals(1, $worker->getJobsCompleted());
        $this->assertGreaterThan(0, $worker->getTotalTimeBusy());
        $this->assertEquals(1, $worker->getOverheadCount());
        $this->assertEquals(time(), $worker->getLastJobDone());
        $this->assertFalse($worker->getHash()->fieldExists(Worker::FIELD_CURRENT_JOB));
        $this->assertFalse($worker->getHash()->fieldExists(Worker::FIELD_CURRENT_QUEUE));
        $this->assertEventFired(Worker::EVENT_JOB_DONE);
    }

    public function testStopAndFailJob()
    {
        $worker = Harness::getInstance()->getWorker('Jack Perkins');

        $queue = Harness::getInstance()->getNewQueue();

        Harness::getInstance()->getPlugin('pump')->getReadyQueueSet()->delete();
        Harness::getInstance()->getPlugin('pump')->getReadyJobList('test')->delete();
        Harness::getInstance()->getPlugin('monitor')->getEventsSortedSet()->delete();

        $queue->queueJob('Cave Dwellers', 'test');

        $this->assertTrue($worker->getNewJob('test'));
        $this->assertTrue($worker->startCurrentJob());

        sleep(1);

        $this->assertTrue($worker->stopCurrentJob());
        $this->assertEquals(Worker::FLAG_STOP, $worker->getHash()->getField(Worker::FIELD_FLAG));

        $this->monitorEvent(Job::EVENT_FAIL);
        $this->monitorEvent(Worker::EVENT_JOB_DONE);

        $job = $worker->getCurrentJob();

        try {
            $worker->progressCurrentJob(1);
        } catch (WorkerStopException $e) {
            $this->assertTrue($worker->failCurrentJob('Your cape is fabulous!'));
        }

        $this->assertEquals(1, $worker->getJobsFailed());
        $this->assertGreaterThan(0, $worker->getTotalTimeBusy());
        $this->assertEquals(1, $worker->getOverheadCount());
        $this->assertEquals(time(), $worker->getLastJobDone());
        $this->assertEquals(Job::STATUS_FAILED, $job->getStatus());
        $this->assertEquals('Your cape is fabulous!', $job->getFailureMessage());
        $this->assertFalse($worker->getHash()->fieldExists(Worker::FIELD_CURRENT_JOB));
        $this->assertFalse($worker->getHash()->fieldExists(Worker::FIELD_CURRENT_QUEUE));
        $this->assertEventFired(Worker::EVENT_JOB_DONE);
    }

    public function testFailAndRetryJob()
    {
        $worker = Harness::getInstance()->getWorker('Jack Perkins');

        $queue = Harness::getInstance()->getNewQueue();

        Harness::getInstance()->getPlugin('pump')->getReadyQueueSet()->delete();
        Harness::getInstance()->getPlugin('pump')->getReadyJobList('test')->delete();
        Harness::getInstance()->getPlugin('monitor')->getEventsSortedSet()->delete();

        $queue->queueJob('Red Zone Cuba', 'test');

        $this->assertTrue($worker->getNewJob('test'));
        $this->assertTrue($worker->startCurrentJob());

        sleep(1);

        $this->monitorEvent(Job::EVENT_FAIL);
        $this->monitorEvent(Worker::EVENT_JOB_DONE);
        $this->monitorEvent(Job::EVENT_REQUEUE);

        $job = $worker->getCurrentJob();

        $retryTime = new \DateTime();
        $retryTime->add(new \DateInterval('PT10S'));

        $this->assertTrue($worker->failCurrentJob('Use the handrails I invented them for a reason', 10));

        $this->assertEquals(2, $worker->getJobsFailed());
        $this->assertGreaterThan(0, $worker->getTotalTimeBusy());
        $this->assertEquals(2, $worker->getOverheadCount());
        $this->assertEquals(time(), $worker->getLastJobDone());
        $this->assertEquals(Job::STATUS_SCHEDULED, $job->getStatus());
        $this->assertEquals($retryTime->format(\DateTime::ISO8601), $job->getScheduledTime()->format(\DateTime::ISO8601));
        $this->assertEquals('Use the handrails I invented them for a reason', $job->getFailureMessage());
        $this->assertFalse($worker->getHash()->fieldExists(Worker::FIELD_CURRENT_JOB));
        $this->assertFalse($worker->getHash()->fieldExists(Worker::FIELD_CURRENT_QUEUE));
        $this->assertEventFired(Worker::EVENT_JOB_DONE);
        $this->assertEventFired(Job::EVENT_FAIL);
        $this->assertEventFired(Job::EVENT_REQUEUE);
    }
}
