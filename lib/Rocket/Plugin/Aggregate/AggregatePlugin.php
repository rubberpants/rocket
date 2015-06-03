<?php

namespace Rocket\Plugin\Aggregate;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\JobEvent;
use Rocket\Job\Job;
use Rocket\Worker\Worker;
use Rocket\Worker\WorkerEvent;

class AggregatePlugin extends AbstractPlugin
{
    protected $allWaitingJobsSet;
    protected $allRunningJobsSet;
    protected $allScheduledJobsSet;
    protected $allWorkersSet;

    public function register()
    {
        $scheduleJobEventHandler = function (JobEvent $event) {
            $this->getAllScheduledJobsSet()->addItem($event->getJob()->getId());
            $this->debug(sprintf('Job %s added to scheduled aggregate sets', $event->getJob()->getId()));
        };

        $addJobEventHandler = function (JobEvent $event) {
            $this->getAllScheduledJobsSet()->deleteItem($event->getJob()->getId());
            $this->getAllWaitingJobsSet()->addItem($event->getJob()->getId());
            $this->debug(sprintf('Job %s added to waiting aggregate sets', $event->getJob()->getId()));
        };

        $startJobEventHandler = function (JobEvent $event) {
            $this->getAllWaitingJobsSet()->moveTo($this->getAllRunningJobsSet(), $event->getJob()->getId());
            $this->debug(sprintf('Job %s added to running aggregate sets', $event->getJob()->getId()));
        };

        $removeJobEventHandler = function (JobEvent $event) {
            $jobId = $event->getJob()->getId();
            $this->getAllWaitingJobsSet()->deleteItem($jobId);
            $this->getAllRunningJobsSet()->deleteItem($jobId);
            $this->debug(sprintf('Job %s removed from aggregate sets', $event->getJob()->getId()));
        };

        $addWorkerEventHandler = function (WorkerEvent $event) {
            $this->getAllWorkersSet()->addItem($event->getWorker()->getWorkerName());
            $this->debug(sprintf('Worker %s added to aggregate sets', $event->getWorker()->getWorkerName()));
        };

        $removeWorkerEventHandler = function (WorkerEvent $event) {
            $this->getAllWorkersSet()->deleteItem($event->getWorker()->getWorkerName());
            $this->debug(sprintf('Worker %s removed from aggregate sets', $event->getWorker()->getWorkerName()));
        };

        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, $scheduleJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE,    $addJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_UNPARK,   $addJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_REQUEUE,  $addJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_START,    $startJobEventHandler);

        $this->getEventDispatcher()->addListener(Job::EVENT_PARK,     $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_CANCEL,   $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL,     $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_DELETE,   $removeJobEventHandler);

        $this->getEventDispatcher()->addListener(Worker::EVENT_ACTIVITY, $addWorkerEventHandler);
        $this->getEventDispatcher()->addListener(Worker::EVENT_DELETE, $removeWorkerEventHandler);
    }

    public function getAllWaitingJobsSet()
    {
        if (is_null($this->allWaitingJobsSet)) {
            $this->allWaitingJobsSet = $this->getRedis()->getSetType('ALL_WAITING_JOBS');
        }

        return $this->allWaitingJobsSet;
    }

    public function getAllRunningJobsSet()
    {
        if (is_null($this->allRunningJobsSet)) {
            $this->allRunningJobsSet = $this->getRedis()->getSetType('ALL_RUNNING_JOBS');
        }

        return $this->allRunningJobsSet;
    }

    public function getAllScheduledJobsSet()
    {
        if (is_null($this->allScheduledJobsSet)) {
            $this->allScheduledJobsSet = $this->getRedis()->getSetType('ALL_SCHEDULED_JOBS');
        }

        return $this->allScheduledJobsSet;
    }

    public function getAllWorkersSet()
    {
        if (is_null($this->allWorkersSet)) {
            $this->allWorkersSet = $this->getRedis()->getSetType('ALL_WORKERS');
        }

        return $this->allWorkersSet;
    }

    public function getAllWaitingJobs()
    {
        return $this->getAllWaitingJobsSet()->getItems();
    }

    public function getAllWaitingJobCount()
    {
        return $this->getAllWaitingJobsSet()->getCount();
    }

    public function getAllRunningJobs()
    {
        return $this->getAllRunningJobsSet()->getItems();
    }

    public function getAllWorkers()
    {
        return $this->getAllWorkersSet()->getItems();
    }

    public function getAllRunningJobCount()
    {
        return $this->getAllRunningJobsSet()->getCount();
    }

    public function getAllScheduledJobs()
    {
        return $this->getAllScheduledJobsSet()->getItems();
    }

    public function getAllScheduledJobCount()
    {
        return $this->getAllScheduledJobsSet()->getCount();
    }

    public function getAllWorkersCount()
    {
        return $this->getAllWorkersSet()->getCount();
    }
}
