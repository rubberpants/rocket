<?php

namespace Rocket\Plugin\UniqueJob;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\Job;
use Rocket\Job\JobInterface;
use Rocket\Job\JobEvent;

class UniqueJobPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;

    protected $activeUniqueJobHash;

    public function register()
    {
        $addJobEventHandler = function (JobEvent $event) {
            $job = $event->getJob();
            $this->getActiveUniqueJobHash()->setField($job->getJobDigest(), $job->getId());
        };

        $removeJobEventHandler = function (JobEvent $event) {
            $job = $event->getJob();
            $this->getActiveUniqueJobHash()->deleteField($job->getJobDigest());
        };

        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, $addJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE,    $addJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL,     $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_CANCEL,   $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_DELETE,   $removeJobEventHandler);
    }

    public function getJobIdIfActive(JobInterface $job)
    {
        return $this->getActiveUniqueJobHash()->getField($job->getJobDigest());
    }

    public function getJobIdIfActiveByDigest($jobDigest)
    {
        return $this->getActiveUniqueJobHash()->getField($jobDigest);
    }

    public function getActiveUniqueJobHash()
    {
        if (is_null($this->activeUniqueJobHash)) {
            $this->activeUniqueJobHash = $this->getRedis()->getHashType('ACTIVE_UNIQUE_JOBS');
        }

        return $this->activeUniqueJobHash;
    }
}
