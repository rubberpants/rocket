<?php

namespace Rocket\Plugin\JobHistory;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\Job;
use Rocket\Job\JobEvent;
use Rocket\Job\JobHistoryEntry;

class JobHistoryPlugin extends AbstractPlugin
{
    public function register()
    {
        /*
            NOTE: Job events NOT recorded in history at this time:
                job.shift
                job.progress
                job.delete
        */

        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, function (JobEvent $event) {
            $job = $event->getJob();
            $job->getHistoryList()->pushItem(
                $this->createEntry(Job::EVENT_SCHEDULE, $job->getScheduledTime())
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE, function (JobEvent $event) {
            $job = $event->getJob();
            $job->getHistoryList()->pushItem(
                $this->createEntry(Job::EVENT_QUEUE, $job->getQueueName())
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_MOVE, function (JobEvent $event) {
            $job = $event->getJob();
            $job->getHistoryList()->pushItem(
                $this->createEntry(Job::EVENT_MOVE, $job->getQueueName())
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_PARK, function (JobEvent $event) {
            $event->getJob()->getHistoryList()->pushItem($this->createEntry(Job::EVENT_PARK));
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_UNPARK, function (JobEvent $event) {
            $event->getJob()->getHistoryList()->pushItem($this->createEntry(Job::EVENT_UNPARK));
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_PAUSE, function (JobEvent $event) {
            $event->getJob()->getHistoryList()->pushItem($this->createEntry(Job::EVENT_PAUSE));
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_RESUME, function (JobEvent $event) {
            $event->getJob()->getHistoryList()->pushItem($this->createEntry(Job::EVENT_RESUME));
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_CANCEL, function (JobEvent $event) {
            $event->getJob()->getHistoryList()->pushItem($this->createEntry(Job::EVENT_CANCEL));
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_DELIVER, function (JobEvent $event) {
            $job = $event->getJob();
            $job->getHistoryList()->pushItem(
                $this->createEntry(Job::EVENT_DELIVER, $job->getWorkerName())
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_START, function (JobEvent $event) {
            $job = $event->getJob();
            $job->getHistoryList()->pushItem(
                $this->createEntry(Job::EVENT_START, $job->getWorkerName())
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, function (JobEvent $event) {
            $event->getJob()->getHistoryList()->pushItem($this->createEntry(Job::EVENT_COMPLETE));
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_ALERT, function (JobEvent $event) {
            $job = $event->getJob();
            $job->getHistoryList()->pushItem(
                $this->createEntry(Job::EVENT_ALERT, $job->getAlertMessage())
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL, function (JobEvent $event) {
            $job = $event->getJob();
            $job->getHistoryList()->pushItem(
                $this->createEntry(Job::EVENT_FAIL, $job->getFailureMessage())
            );
        });
    }

    protected function createEntry($eventName, $details = '')
    {
        $entry = new JobHistoryEntry();
        $entry->setEventName($eventName);
        $entry->setDetails($details);
        $entry->setTimestamp(new \DateTime());

        return (string) $entry;
    }
}
