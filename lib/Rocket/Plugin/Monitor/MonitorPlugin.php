<?php

namespace Rocket\Plugin\Monitor;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\JobEvent;
use Rocket\Job\Job;
use Rocket\Job\JobInterface;
use Symfony\Component\Process\Process;

class MonitorPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;

    const EVENT_JOB_ALERT = 'monitor.job_alert';

    const ACTION_JOB_EXPIRE = 'job.expire';
    const ACTION_JOB_ALERT  = 'job.alert';

    protected $process;
    protected $eventsSortedSet;
    protected $monitorActivityString;
    protected $stopMonitorString;

    public function register()
    {
        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE, function (JobEvent $event) {
            $this->addJobStatusEvent(
                self::ACTION_JOB_ALERT,
                $event->getJob(),
                Job::STATUS_WAITING,
                $this->getConfig()->getMonitorWaitingJobMax()
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_DELIVER, function (JobEvent $event) {
            $this->addJobStatusEvent(
                self::ACTION_JOB_ALERT,
                $event->getJob(),
                Job::STATUS_DELIVERED,
                $this->getConfig()->getMonitorDeliveredJobMax()
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_START, function (JobEvent $event) {
            $this->addJobStatusEvent(
                self::ACTION_JOB_ALERT,
                $event->getJob(),
                Job::STATUS_RUNNING,
                $event->getJob()->getMaxRuntime() ?: $this->getConfig()->getMonitorDefaultRunningJobMax()
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, function (JobEvent $event) {
            $this->addJobStatusEvent(
                self::ACTION_JOB_EXPIRE,
                $event->getJob(),
                Job::STATUS_COMPLETED,
                $this->getConfig()->getMonitorCompletedJobTTL()
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL, function (JobEvent $event) {
            $this->addJobStatusEvent(
                self::ACTION_JOB_EXPIRE,
                $event->getJob(),
                Job::STATUS_FAILED,
                $this->getConfig()->getMonitorFailedJobTTL()
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_CANCEL, function (JobEvent $event) {
             $this->addJobStatusEvent(
                self::ACTION_JOB_EXPIRE,
                $event->getJob(),
                Job::STATUS_CANCELLED,
                $this->getConfig()->getMonitorCancelledJobTTL()
            );
        });
    }

    public function addJobStatusEvent($action, Job $job, $status, $ttl)
    {
        $this->getEventsSortedSet()->addItem(
            time()+$ttl,
            json_encode([
                $action,
                $job->getQueue()->getQueueName(),
                $job->getId(),
                $status,
            ])
        );
    }

    public function getEventsSortedSet()
    {
        if (is_null($this->eventsSortedSet)) {
            $this->eventsSortedSet = $this->getRedis()->getSortedSetType('MONITOR_EVENTS');
        }

        return $this->eventsSortedSet;
    }

    public function execute($maxEventsToHandle)
    {
        foreach ($this->getEventsSortedSet()->getItems(0, time(), $maxEventsToHandle) as $event) {
            $this->getEventsSortedSet()->deleteItem($event);

            list($action, $queueName, $jobId, $status) = json_decode($event, true);

            $this->debug('Recieved task: '.$event);

            if ($action == self::ACTION_JOB_EXPIRE || $action == self::ACTION_JOB_ALERT) {
                $this->handleJobAction($this->getRocket()->getJob($jobId, $queueName), $action, $status);
            } else {
                $this->warning(sprintf('Unknown monitor action: %s', $action));
            }
        }
    }

    public function handleJobAction(JobInterface $job, $action, $status)
    {
        if ($job) {
            if ($job->getStatus() === $status) {
                switch ($action) {
                    case self::ACTION_JOB_EXPIRE:
                        $this->debug(sprintf('Job %s has expired', $job->getId()));
                        $job->delete();
                        break;
                    case self::ACTION_JOB_ALERT:
                        $this->warning($msg = sprintf('Job %s status %s for too long', $job->getId(), $status));
                        $job->setAlert($msg);
                        $this->getEventDispatcher()->dispatch(self::EVENT_JOB_ALERT, new JobEvent($job));
                        break;
                }
            }
        } else {
            $this->info(sprintf('Job %s no longer exists', $jobId));
        }
    }
}
