<?php

namespace Rocket\Plugin\UniqueJob;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\Job;
use Rocket\Job\JobEvent;

class UniqueJobPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;

    protected $activeUniqueJobHash;

    public function register()
    {
        $addJobEventHandler = function (JobEvent $event) {
            $job = $event->getJob();
            $hash = $this->hashJob($job->getJob());
            $this->getActiveUniqueJobHash()->setField($hash, $job->getId());
        };

        $removeJobEventHandler = function (JobEvent $event) {
            $job = $event->getJob();
            $hash = $this->hashJob($job->getJob());
            $this->getActiveUniqueJobHash()->deleteField($hash);
        };

        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, $addJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE,    $addJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL,     $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_CANCEL,   $removeJobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_DELETE,   $removeJobEventHandler);
    }

    public function getJobIdIfActive($jobData)
    {
        return $this->getActiveUniqueJobHash()->getField($this->hashJob($jobData));
    }

    public function getActiveUniqueJobHash()
    {
        if (is_null($this->activeUniqueJobHash)) {
            $this->activeUniqueJobHash = $this->getRedis()->getHashType('ACTIVE_UNIQUE_JOBS');
        }

        return $this->activeUniqueJobHash;
    }

    protected function hashJob($jobData)
    {
        return sha1($jobData);
    }
}
