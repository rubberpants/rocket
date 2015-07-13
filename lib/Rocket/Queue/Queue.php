<?php

namespace Rocket\Queue;

use Rocket\RocketInterface;
use Rocket\RocketException;
use Rocket\Job\Job;
use Rocket\Job\JobInterface;
use Rocket\Job\JobEvent;

class Queue implements QueueInterface
{
    use QueueDataStructuresTrait;
    use \Rocket\ObjectCacheTrait;
    use \Rocket\LogTrait;
    use \Rocket\Plugin\EventTrait;
    use \Rocket\Config\ConfigTrait;

    const EVENT_INIT    = 'queue.init';
    const EVENT_UPDATE  = 'queue.update';
    const EVENT_PAUSE   = 'queue.pause';
    const EVENT_RESUME  = 'queue.resume';
    const EVENT_DELETE  = 'queue.delete';
    const EVENT_DISABLE = 'queue.disable';
    const EVENT_ENABLE  = 'queue.enable';
    const EVENT_FULL    = 'queue.full';

    protected $rocket;
    protected $queueName;

    /**
     * Generate a UUID version 4 string. Used as the default behavior
     * for assigning new job IDs.
     *
     * @return string
     */
    public static function generateUUIDv4()
    {
        return sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
            mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 4095),
            bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
            mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
        );
    }

    /**
     * Create the queue object. Specify the name of the queue.
     *
     * @return Queue
     */
    public function __construct(RocketInterface $rocket, $queueName)
    {
        $this->rocket = $rocket;
        $this->queueName = $queueName;
        $this->setConfig($rocket->getConfig());
        $this->setRedis($rocket->getRedis());
        $this->setLogger($rocket->getLogger(), $rocket->getLogContext());
        $this->SetEventDispatcher($rocket->getEventDispatcher());
        $this->setLogContext('queue', $queueName);
    }

    /**
     * Get the name of the queue.
     *
     * @return string
     */
    public function getQueueName()
    {
        return $this->queueName;
    }

    /**
     * Initiallize the data structures of the queue if they aren't already. Automatically
     * called when needed by other queue methods.
     */
    public function init()
    {
        if (!$this->getQueuesSet()->hasItem($this->getQueueName())) {
            $this->getRedis()->openPipeline();
            $this->getQueuesSet()->addItem($this->getQueueName());
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_INIT, new QueueEvent($this));

            $this->info('Queue initialized');
        }
    }

    /**
     * Return a summary structure of the meta-data for this queue. This is a somewhat
     * intensive call, so use sparingly.
     *
     * @return array
     */
    public function getInfo()
    {
        $pump = $this->rocket->getPlugin('pump');

        return [
            'name'                  => $this->getQueueName(),
            'waiting_limit'         => $this->getWaitingLimit(),
            'min_running_limit'     => $this->getMinRunningLimit(),
            'max_running_limit'     => $this->getMaxRunningLimit(),
            'current_running_limit' => $pump->getCurrentRunningLimit($this),
            'is_disabled'           => $this->isDisabled(),
            'is_paused'             => $this->isPaused(),
            'scheduled_jobs'        => $this->getScheduledJobCount(),
            'waiting_jobs'          => $this->getWaitingJobCount(),
            'running_jobs'          => $this->getRunningJobCount(),
            'paused_jobs'           => $this->getPausedJobCount(),
            'parked_jobs'           => $this->getParkedJobCount(),
            'cancelled_jobs'        => $this->getCancelledJobCount(),
            'completed_jobs'        => $this->getCompletedJobCount(),
            'failed_jobs'           => $this->getFailedJobCount(),
        ];
    }

    /**
     * Schedule a job to be queued at a specific time. Optionally specify the id to use for the
     * new job. Returns the new job object. Optionally include the runtime before
     * a job will alert.
     *
     * @param DateTime $time
     * @param string   $jobData
     * @param string   $type
     * @param string   $id
     * @param int      $maxRuntime
     *
     * @return Job | boolean
     */
    public function scheduleJob(\DateTime $time, $jobData, $type = 'default', $id = null, $maxRuntime = 0)
    {
        $this->init();

        if (is_null($id)) {
            $id = self::generateUUIDv4();
            $this->info(sprintf('Assigning id %s to new job', $id));
        }

        $job = $this->initNewJobObject($id, $type, $jobData, $maxRuntime);

        $this->getRedis()->openPipeline();
        $job->getHash()->setField(Job::FIELD_SCHEDULE_TIME, $time->format(\DateTime::ISO8601));
        $job->getHash()->setField(Job::FIELD_STATUS, Job::STATUS_SCHEDULED);
        $this->getScheduledSet()->addItem($job->getId());
        $this->getScheduledSortedSet()->addItem($time->getTimestamp(), json_encode([$job->getId(), $this->getQueueName()]));
        $this->getRedis()->closePipeline();

        $this->getEventDispatcher()->dispatch(Job::EVENT_SCHEDULE, new JobEvent($job));

        $this->info(sprintf('Job %s scheduled in queue for %s', $id, $time->format(\DateTime::ISO8601)));

        return $job;
    }

    /**
     * Queue the job payload at the end of this queue. Optionally specify the id to use for the
     * new job. Returns the new job object. If the queue is disabled, returns false. If the queue
     * is already at it's waiting job limit, returns false. Optionally include the runtime before
     * a job will alert.
     *
     * @param string $jobData
     * @param string $type
     * @param string $id
     * @param int    $maxRuntime
     *
     * @return Job | boolean
     */
    public function queueJob($jobData, $type = 'default', $id = null, $maxRuntime = 0)
    {
        if ($this->isDisabled()) {
            $this->getEventDispatcher()->dispatch(self::EVENT_FULL, new QueueFullEvent($this, $jobData));
            $this->warning(sprintf('Cannot queue job %s because queue is disabled', $id));

            throw new RocketException(sprintf('Cannot queue job %s because queue is disabled', $id));
        }

        $this->init();

        if ($this->getWaitingLimit() > 0 && $this->getWaitingJobCount() >= $this->getWaitingLimit()) {
            $this->getEventDispatcher()->dispatch(self::EVENT_FULL, new QueueFullEvent($this, $jobData));
            $this->warning(sprintf('Cannot queue job %s because waiting limit was reached', substr($jobData, 0, 10)."..."));

            throw new RocketException(sprintf('Cannot queue job %s because waiting limit was reached', substr($jobData, 0, 10)."..."));
        }

        if (is_null($id)) {
            $id = self::generateUUIDv4();
            $this->info(sprintf('Assigning id %s to new job', $id));
        }

        $job = $this->initNewJobObject($id, $type, $jobData, $maxRuntime);

        $this->getRedis()->openPipeline();
        $job->getHash()->setField(Job::FIELD_QUEUE_TIME,  (new \DateTime())->format(\DateTime::ISO8601));
        $job->getHash()->setField(Job::FIELD_STATUS, Job::STATUS_WAITING);
        $this->getWaitingSet()->addItem($job->getId());
        $this->getWaitingList()->pushItem($job->getId());
        $this->getScheduledSet()->deleteItem($job->getId());
        $this->getRedis()->closePipeline();

        $this->getEventDispatcher()->dispatch(Job::EVENT_QUEUE, new JobEvent($job));

        $this->info(sprintf('Job %s added to queue', $id));

        return $job;
    }

    /**
     * Move the waiting job from it's current to this queue.
     * If the job is already in this queue or is not waiting, false is returned.
     *
     * @param JobInterface $job
     *
     * @return boolean
     */
    public function moveJob(JobInterface &$job)
    {
        if ($job->getQueueName() == $this->getQueueName()) {
            return false;
        }

        $isParked = ($job->getStatus() == Job::STATUS_PARKED);

        if ($job->getStatus() !== Job::STATUS_WAITING && !$isParked) {
            $this->warning(sprintf('Cannot move job %s because it is not waiting or parked', $job->getId()));

            throw new RocketException(sprintf('Cannot move job %s because it is not waiting or parked', $job->getId()));
        }

        if ($isParked || $job->getQueue()->getWaitingList()->deleteItem($job->getId())) {
            $this->init();
            $this->getRedis()->openPipeline();
            if ($isParked) {
                $this->getParkedSet()->moveFrom($job->getQueue()->getParkedSet(), $job->getId());
            } else {
                $this->getWaitingSet()->moveFrom($job->getQueue()->getWaitingSet(), $job->getId());
            }
            $job->getHash()->setField(Job::FIELD_QUEUE_NAME, $this->getQueueName());
            $this->getWaitingList()->pushItem($job->getId());
            $this->getRedis()->closePipeline();

            $job = new Job($job->getId(), $this);

            $this->getEventDispatcher()->dispatch(Job::EVENT_MOVE, new JobEvent($job));

            $this->info(sprintf('Job %s moved to queue %s', $job->getId(), $this->getQueueName()));

            return true;
        }

        $this->warning(sprintf('Failed to move job %s to queue %s', $job->getId(), $this->getQueueName()));

        throw new RocketException(sprintf('Failed to move job %s to queue %s', $job->getId(), $this->getQueueName()));
    }

    /**
     * Get the minimum allowable running limit for this queue.
     *
     * @return integer
     */
    public function getMinRunningLimit()
    {
        return $this->getConfig()->getQueuesMinRunningLimit($this->getQueueName());
    }

    /**
     * Get the maximum possible running limit for this queue.
     *
     * @return integer
     */
    public function getMaxRunningLimit()
    {
        return $this->getConfig()->getQueuesMaxRunningLimit($this->getQueueName());
    }

    /**
     * Get the number of jobs allowed to be waiting in this queue.
     *
     * @return integer
     */
    public function getWaitingLimit()
    {
        return $this->getConfig()->getQueuesWaitingLimit($this->getQueueName());
    }

    /**
     * Prevent jobs from being queue to this queue.
     */
    public function disable()
    {
        $this->getDisabledString()->on();
        $this->getEventDispatcher()->dispatch(self::EVENT_DISABLE, new QueueEvent($this));
        $this->info('Queue disabled');
    }

    /**
     * Allow jobs to be queued to this queue.
     */
    public function enable()
    {
        $this->getDisabledString()->off();
        $this->getEventDispatcher()->dispatch(self::EVENT_ENABLE, new QueueEvent($this));
        $this->info('Queue enabled');
    }

    /**
     * Prevent jobs from this queue from being delivered.
     */
    public function pause()
    {
        $this->getPausedString()->on();
        $this->getEventDispatcher()->dispatch(self::EVENT_PAUSE, new QueueEvent($this));
        $this->info('Queue paused');
    }

    /**
     * Allow jobs from this queue to be delivered.
     */
    public function resume()
    {
        $this->getPausedString()->off();
        $this->getEventDispatcher()->dispatch(self::EVENT_RESUME, new QueueEvent($this));
        $this->info('Queue resumed');
    }

    /**
     * Simply adds the queue to the ready list as a backup.
     */
    public function update()
    {
        $this->getEventDispatcher()->dispatch(self::EVENT_UPDATE, new QueueEvent($this));
        $this->info('Queue updated');
    }

    /**
     * Remove this queue and all references to it from the system.
     * Queue must not have any jobs.
     *
     * @return boolean
     */
    public function delete()
    {
        if ($this->getAllJobCount() > 0) {
            $this->warning('Cannot delete queue because it still has jobs');

            throw new RocketException('Cannot delete queue because it still has jobs');
        }

        if ($this->getQueuesSet()->deleteItem($this->getQueueName())) {
            $this->getRedis()->openPipeline();
            $this->getWaitingList()->delete();
            $this->getWaitingSet()->delete();
            $this->getParkedSet()->delete();
            $this->getRunningSet()->delete();
            $this->getCancelledSet()->delete();
            $this->getFailedSet()->delete();
            $this->getCompletedSet()->delete();
            $this->getScheduledSet()->delete();
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_DELETE, new QueueEvent($this));
            $this->info('Queue deleted');

            return true;
        }

        $this->warning('Failed to delete queue');

        throw new RocketException('Failed to delete queue');
    }

    public function isDisabled()
    {
        return $this->getDisabledString()->isOn();
    }

    public function isPaused()
    {
        return $this->getPausedString()->isOn();
    }

    public function getJob($jobId)
    {
        return $this->getCachedObject('job', $jobId, function ($jobId) {
            return new Job($jobId, $this);
        });
    }

    public function flushJobsByStatus($status)
    {
        foreach ((array) $this->getJobsByStatus($status) as $jobId) {
            $this->getJob($jobId)->delete();
        }
    }

    public function getJobsByStatus($status)
    {
        switch ($status) {
            case Job::STATUS_SCHEDULED:
                return $this->getScheduledJobs();
            case Job::STATUS_WAITING:
                return $this->getWaitingJobs();
            case Job::STATUS_RUNNING:
                return $this->getRunningJobs();
            case Job::STATUS_PARKED:
                return $this->getParkedJobs();
            case Job::STATUS_CANCELLED:
                return $this->getCancelledJobs();
            case Job::STATUS_FAILED:
                return $this->getFailedJobs();
            case Job::STATUS_COMPLETED:
                return $this->getCompletedJobs();
        }
    }

    public function getWaitingJobs()
    {
        return $this->getWaitingSet()->getItems();
    }

    public function getWaitingJobsByPage($page, $pageSize)
    {
        return $this->getWaitingList()->getItems($page, $pageSize);
    }

    public function getWaitingJobCount()
    {
        return $this->getWaitingSet()->getCount();
    }

    public function getRunningJobs()
    {
        return $this->getRunningSet()->getItems();
    }

    public function getRunningJobCount()
    {
        return $this->getRunningSet()->getCount();
    }

    public function getPausedJobs()
    {
        return $this->getPausedSet()->getItems();
    }

    public function getPausedJobCount()
    {
        return $this->getPausedSet()->getCount();
    }

    public function getParkedJobs()
    {
        return $this->getParkedSet()->getItems();
    }

    public function getParkedJobCount()
    {
        return $this->getParkedSet()->getCount();
    }

    public function getActiveJobs()
    {
        return array_merge($this->getWaitingJobs(), $this->getRunningJobs(), $this->getParkedJobs());
    }

    public function getActiveJobCount()
    {
        return $this->getWaitingJobCount() + $this->getRunningJobCount() + $this->getParkedJobCount();
    }

    public function getCancelledJobs()
    {
        return $this->getCancelledSet()->getItems();
    }

    public function getCancelledJobCount()
    {
        return $this->getCancelledSet()->getCount();
    }

    public function getCompletedJobs()
    {
        return $this->getCompletedSet()->getItems();
    }

    public function getCompletedJobCount()
    {
        return $this->getCompletedSet()->getCount();
    }

    public function getFailedJobs()
    {
        return $this->getFailedSet()->getItems();
    }

    public function getFailedJobCount()
    {
        return $this->getFailedSet()->getCount();
    }

    public function getInactiveJobs()
    {
        return array_merge($this->getCancelledJobs(), $this->getFailedJobs(), $this->getCompletedJobs());
    }

    public function getInactiveJobCount()
    {
        return $this->getCancelledJobCount() + $this->getFailedJobCount() + $this->getCompletedJobCount();
    }

    public function getAllJobs()
    {
        return array_merge($this->getActiveJobs(), $this->getInactiveJobs());
    }

    public function getAllJobCount()
    {
        return $this->getActiveJobCount() + $this->getInactiveJobCount();
    }

    public function getScheduledJobs()
    {
        return $this->getScheduledSet()->getItems();
    }

    public function getScheduledJobCount()
    {
        return $this->getScheduledSet()->getCount();
    }

    public function getScheduledSortedSet()
    {
        return $this->rocket->getPlugin('pump')->getScheduledSortedSet();
    }

    protected function initNewJobObject($id, $type, $jobData, $maxRuntime)
    {
        $job = new Job($id, $this);

        $this->getRedis()->openPipeline();
        $job->getHash()->setField(Job::FIELD_ID,          $id);
        $job->getHash()->setField(Job::FIELD_TYPE,        $type);
        $job->getHash()->setField(Job::FIELD_QUEUE_NAME,  $this->getQueueName());
        $job->getHash()->setField(Job::FIELD_JOB,         $jobData);
        $job->getHash()->setField(Job::FIELD_MAX_RUNTIME, $maxRuntime);
        $this->getRedis()->closePipeline();

        return $job;
    }
}
