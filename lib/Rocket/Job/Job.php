<?php

namespace Rocket\Job;

use Rocket\RocketException;
use Rocket\Queue\QueueInterface;
use Rocket\Queue\Queue;

class Job implements JobInterface
{
    use \Rocket\LogTrait;
    use \Rocket\Config\ConfigTrait;
    use \Rocket\Plugin\EventTrait;
    use \Rocket\Redis\RedisTrait;

    const EVENT_SCHEDULE = 'job.schedule';
    const EVENT_QUEUE    = 'job.queue';
    const EVENT_MOVE     = 'job.move';
    const EVENT_SHIFT    = 'job.shift';
    const EVENT_PARK     = 'job.park';
    const EVENT_UNPARK   = 'job.unpark';
    const EVENT_PAUSE    = 'job.pause';
    const EVENT_RESUME   = 'job.resume';
    const EVENT_CANCEL   = 'job.cancel';
    const EVENT_DELIVER  = 'job.deliver';
    const EVENT_START    = 'job.start';
    const EVENT_STOP     = 'job.stop';
    const EVENT_REQUEUE  = 'job.requeue';
    const EVENT_DELETE   = 'job.delete';
    const EVENT_PROGRESS = 'job.progress';
    const EVENT_COMPLETE = 'job.complete';
    const EVENT_FAIL     = 'job.fail';
    const EVENT_ALERT    = 'job.alert';

    const FIELD_ID            = 'id';
    const FIELD_TYPE          = 'type';
    const FIELD_STATUS        = 'status';
    const FIELD_PROGRESS      = 'progress';
    const FIELD_MAX_RUNTIME   = 'max_runtime';
    const FIELD_JOB           = 'job';
    const FIELD_JOB_DIGEST    = 'job_digest';
    const FIELD_QUEUE_NAME    = 'queue_name';
    const FIELD_WORKER_NAME   = 'worker_name';
    const FIELD_SCHEDULE_TIME = 'sched_time';
    const FIELD_QUEUE_TIME    = 'queue_time';
    const FIELD_DELIVER_TIME  = 'deliver_time';
    const FIELD_START_TIME    = 'start_time';
    const FIELD_COMPLETE_TIME = 'complete_time';
    const FIELD_FAIL_TIME     = 'fail_time';
    const FIELD_CANCEL_TIME   = 'cancel_time';
    const FIELD_PREV_QUEUE    = 'prev_queue';
    const FIELD_IS_ALERTING   = 'is_alerting';
    const FIELD_FAILURE_MSG   = 'failure_message';
    const FIELD_ALERT_MSG     = 'alert_message';
    const FIELD_ATTEMPTS      = 'attempts';

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_WAITING   = 'waiting';
    const STATUS_PARKED    = 'parked';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_RUNNING   = 'running';
    const STATUS_PAUSED    = 'paused';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED    = 'failed';
    const STATUS_COMPLETED = 'completed';

    protected $id;
    protected $queue;
    protected $hash;
    protected $historyList;
    protected $history;

    public function __construct($jobId, QueueInterface $queue)
    {
        $this->id = $jobId;
        $this->setConfig($queue->getConfig());
        $this->setRedis($queue->getRedis());
        $this->setEventDispatcher($queue->getEventDispatcher());
        $this->setLogger($queue->getLogger(), $queue->getLogContext()); //worker log context too?
        $this->setLogContext('job', $jobId);
        $this->queue = $queue;
    }

    /**
     * Get a redis type wrapper for the job data hash.
     *
     * @return HashType
     */
    public function getHash()
    {
        if (is_null($this->hash)) {
            $this->hash = $this->getRedis()->getHashType(sprintf('JOB:%s', $this->getId()));
        }

        return $this->hash;
    }

    /**
     * Get a redis type wrapper for the job history list.
     *
     * @return ListType
     */
    public function getHistoryList()
    {
        if (is_null($this->historyList)) {
            $this->historyList = $this->getRedis()->getListType(sprintf('JOB:%s:HISTORY', $this->getId()));
        }

        return $this->historyList;
    }

    /**
     * Get the unique id assigned to the job.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the status of the job.
     *
     * @return string
     *                STATUS_SCHEDULED
     *                STATUS_WAITING
     *                STATUS_PARKED
     *                STATUS_DELIVERED
     *                STATUS_RUNNING
     *                STATUS_PAUSED
     *                STATUS_CANCELLED
     *                STATUS_FAILED
     *                STATUS_COMPLETED
     */
    public function getStatus()
    {
        return $this->getHash()->getField(self::FIELD_STATUS);
    }

    /**
     * Get the job type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->getHash()->getField(self::FIELD_TYPE);
    }

    /**
     * Get the estimated runtime of the job in seconds.
     *
     * @return string
     */
    public function getMaxRuntime()
    {
        return $this->getHash()->getField(self::FIELD_MAX_RUNTIME);
    }

    /**
     * Get the current progress of the job (if populated by the worker).
     *
     * @return string
     */
    public function getProgress()
    {
        return $this->getHash()->getField(self::FIELD_PROGRESS);
    }

    /**
     * Get the queue object where the job lives.
     *
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Get the name of the queue where the job lives.
     *
     * @return string
     */
    public function getQueueName()
    {
        return $this->getHash()->getField(self::FIELD_QUEUE_NAME);
    }

    /**
     * If the job is running, get the name of the worker currently processing the request.
     *
     * @return string
     */
    public function getWorkerName()
    {
        return $this->getHash()->getField(self::FIELD_WORKER_NAME);
    }

    /**
     * Get the time the job is scheduled to be queued.
     *
     * @return DateTime
     */
    public function getScheduledTime()
    {
        return new \DateTime($this->getHash()->getField(self::FIELD_SCHEDULE_TIME));
    }

    /**
     * Get the time the job was queued.
     *
     * @return DateTime
     */
    public function getQueueTime()
    {
        return new \DateTime($this->getHash()->getField(self::FIELD_QUEUE_TIME));
    }

    /**
     * Get the time the job was delivered to a worker.
     *
     * @return DateTime
     */
    public function getDeliverTime()
    {
        return new \DateTime($this->getHash()->getField(self::FIELD_DELIVER_TIME));
    }

    /**
     * Get the time the job was started.
     *
     * @return DateTime
     */
    public function getStartTime()
    {
        return new \DateTime($this->getHash()->getField(self::FIELD_START_TIME));
    }

    /**
     * Get the time the job was completed.
     *
     * @return DateTime
     */
    public function getCompleteTime()
    {
        return new \DateTime($this->getHash()->getField(self::FIELD_COMPLETE_TIME));
    }

    /**
     * Get the time the job failed.
     *
     * @return DateTime
     */
    public function getFailTime()
    {
        return new \DateTime($this->getHash()->getField(self::FIELD_FAIL_TIME));
    }

    /**
     * Get the time the job was cancelled.
     *
     * @return DateTime
     */
    public function getCancelTime()
    {
        return new \DateTime($this->getHash()->getField(self::FIELD_CANCEL_TIME));
    }

    /**
     * Returns the true if the job is in an alert state.
     *
     * @return boolean
     */
    public function isAlerting()
    {
        return (bool) $this->getHash()->getField(self::FIELD_IS_ALERTING);
    }

    /**
     * Returns the reason for the job alert.
     *
     * @return boolean
     */
    public function getAlertMessage()
    {
        return $this->getHash()->getField(self::FIELD_ALERT_MSG);
    }

    /**
     * Returns the reason for the job failure.
     *
     * @return boolean
     */
    public function getFailureMessage()
    {
        return $this->getHash()->getField(self::FIELD_FAILURE_MSG);
    }

    /**
     * Put the job in an alert state.
     */
    public function setAlert($alertMessage)
    {
        $this->getHash()->setField(self::FIELD_IS_ALERTING, 1);
        $this->getHash()->setField(self::FIELD_ALERT_MSG, $alertMessage);
        $this->getEventDispatcher()->dispatch(self::EVENT_ALERT, new JobEvent($this));
    }

    /**
     * Clear the alert state.
     */
    public function clearAlert()
    {
        $this->getHash()->deleteField(self::FIELD_IS_ALERTING);
        $this->getHash()->deleteField(self::FIELD_ALERT_MSG);
    }

    /**
     * Get the payload associated with the job.
     *
     * @return string
     */
    public function getJob()
    {
        return $this->getHash()->getField(self::FIELD_JOB);
    }

    /**
     * Get the unique message digest for the job. If one wasn't specified when the job was
     * created, the SHA1 hash of the payload is used.
     *
     * @return string
     */
    public function getJobDigest()
    {
        if ($digest = $this->getHash()->getField(self::FIELD_JOB_DIGEST)) {
            return $digest;
        } else {
            return sha1($this->getHash()->getField(self::FIELD_JOB));
        }
    }

    /**
     * Get the number of times a job as been started.
     *
     * @return integer
     */
    public function getAttempts()
    {
        return $this->getHash()->getField(self::FIELD_ATTEMPTS);
    }

    /**
     * Prioritize the job ahead of the specified job id.
     *
     * @param string $pivot
     */
    public function shiftBefore($pivot)
    {
        return $this->shift($pivot, 'BEFORE');
    }

    /**
     * Prioritize the job behind of the specified job id.
     *
     * @param string $pivot
     */
    public function shiftAfter($pivot)
    {
        return $this->shift($pivot, 'AFTER');
    }

    /**
     * Get the list of events that happened to the job.
     *
     * @return Array of JobHistoryEntry
     */
    public function getHistory()
    {
        if (is_null($this->history)) {
            foreach ($this->getHistoryList()->getItems() as $entry) {
                $this->history[] = JobHistoryEntry::createFromString($entry);
            }
        }

        return $this->history;
    }

    /**
     * Prioritize the job relative of the specified job id.
     *
     * @param string $pivot
     * @param string $position
     *                         BEFORE
     *                         AFTER
     *
     * @return boolean
     */
    public function shift($pivot, $position)
    {
        if ($this->getQueue()->getWaitingList()->deleteItem($this->getId())) {
            $this->getRedis()->openPipeline();
            $this->getQueue()->getWaitingList()->insertItem($this->getId(), $position, $pivot);
            $this->getRedis()->closePipeline();
            $this->getEventDispatcher()->dispatch(self::EVENT_SHIFT, new JobEvent($this));
            $this->info(sprintf('Job shifted %s %s', $position, $pivot));

            return true;
        }

        $this->warning('Job shift failed');

        throw new RocketException('Job shift failed');
    }

    /**
     * Park a waiting job. A parked job will not be delivered to any workers and will
     * not affect other jobs in the queue.
     *
     * @return boolean
     */
    public function park()
    {
        if ($this->getQueue()->getWaitingList()->deleteItem($this->getId())) {
            $this->getRedis()->openPipeline();
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_PARKED);
            $this->getQueue()->getWaitingSet()->moveTo($this->getQueue()->getParkedSet(), $this->getId());
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_PARK, new JobEvent($this));
            $this->info('Job parked');

            return true;
        }

        $this->warning('Job park failed');

        throw new RocketException('Job park failed');
    }

    /**
     * Resume a parked job. The job will be placed at the back of the queue.
     *
     * @return boolean
     */
    public function unpark()
    {
        if ($this->getQueue()->getWaitingSet()->moveFrom($this->getQueue()->getParkedSet(), $this->getId())) {
            $this->getRedis()->openPipeline();
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_WAITING);
            $this->getQueue()->getWaitingList()->pushItem($this->getId());
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_UNPARK, new JobEvent($this));
            $this->info('Job unparked');

            return true;
        }

        $this->warning('Job unpark failed');

        throw new RocketException('Job unpark failed');
    }

    /**
     * Pause a running job.
     *
     * @return boolean
     */
    public function pause()
    {
        if ($this->getStatus() != self::STATUS_RUNNING) {
            $this->warning('Cannot pause job because it is not running');
            throw new RocketException('Cannot pause job because it is not running');
        }

        if ($this->getQueue()->getRunningSet()->moveTo($this->getQueue()->getPausedSet(), $this->getId())) {
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_PAUSED);

            $this->getEventDispatcher()->dispatch(self::EVENT_PAUSE, new JobEvent($this));
            $this->info('Job pause');

            return true;
        }

        throw new RocketException('Job pause failed');
    }

    /**
     * Resume a paused job.
     *
     * @return boolean
     */
    public function resume()
    {
        if ($this->getStatus() != self::STATUS_PAUSED) {
            $this->warning('Cannot resume job because it is not paused');
            throw new RocketException('Cannot resume job because it is not paused');
        }

        if ($this->getQueue()->getRunningSet()->moveFrom($this->getQueue()->getPausedSet(), $this->getId())) {
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_RUNNING);

            $this->getEventDispatcher()->dispatch(self::EVENT_RESUME, new JobEvent($this));
            $this->info('Job resume');

            return true;
        }

        throw new RocketException('Job resume failed');
    }

    /**
     * Cancel a scheduled, waiting, or parked job. Running jobs cannot be cancelled at this time.
     * The job is taken out of the queue and is deleted after the expiration period.
     * A cancelled job can be requeued with the requeue() method.
     *
     * @return boolean
     */
    public function cancel()
    {
        if ($this->getStatus() != self::STATUS_SCHEDULED &&
            $this->getStatus() != self::STATUS_WAITING &&
            $this->getStatus() != self::STATUS_PARKED) {
            $this->warning('Cannot cancel job because it is not scheduled, waiting, or parked');
            throw new RocketException('Cannot cancel job because it is not scheduled, waiting, or parked');
        }

        $this->getRedis()->openPipeline();
        if ($this->getStatus() == self::STATUS_SCHEDULED) {
            $this->getQueue()->getScheduledSortedSet()->deleteItem($this->getId());
        } else {
            $this->getQueue()->getWaitingList()->deleteItem($this->getId());
        }
        $this->getQueue()->getScheduledSet()->moveTo($this->getQueue()->getCancelledSet(), $this->getId());
        $this->getQueue()->getWaitingSet()->moveTo($this->getQueue()->getCancelledSet(), $this->getId());
        $this->getQueue()->getParkedSet()->moveTo($this->getQueue()->getCancelledSet(), $this->getId());
        $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_CANCELLED);
        $this->getHash()->setField(self::FIELD_CANCEL_TIME, (new \DateTime())->format(\DateTime::ISO8601));
        $this->getHash()->deleteField(self::FIELD_WORKER_NAME);
        $this->getRedis()->closePipeline();

        $this->getEventDispatcher()->dispatch(self::EVENT_CANCEL, new JobEvent($this));
        $this->info('Job cancelled');

        return true;
    }

    /**
     * Delete a job. The job and all references to it are removed from the system.
     * CAUTION: This cannot be undone.
     *
     * @return boolean
     */
    public function delete()
    {
        if ($this->getHash()->delete()) {
            $this->getRedis()->openPipeline();
            $this->getHistoryList()->delete();
            $this->getQueue()->getWaitingList()->deleteItem($this->getId());
            $this->getQueue()->getWaitingSet()->deleteItem($this->getId());
            $this->getQueue()->getParkedSet()->deleteItem($this->getId());
            $this->getQueue()->getRunningSet()->deleteItem($this->getId());
            $this->getQueue()->getCancelledSet()->deleteItem($this->getId());
            $this->getQueue()->getFailedSet()->deleteItem($this->getId());
            $this->getQueue()->getCompletedSet()->deleteItem($this->getId());
            $this->getQueue()->getScheduledSet()->deleteItem($this->getId());
            $this->getQueue()->getScheduledSortedSet()->deleteItem($this->getId());
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_DELETE, new JobEvent($this));
            $this->info('Job deleted');

            return true;
        }

        $this->warning('Job delete failed');

        throw new RocketException('Job delete failed');
    }

    /**
     * Requeue a job. Puts the job back in the queue and sets the status to waiting.
     * This is primarily used to retry failed jobs. Optionally specify a time
     * in the future the job should be put back in the queue.
     *
     * @param DateTime $time
     *
     * @return boolean
     */
    public function requeue(\DateTime $time = null)
    {
        $this->getRedis()->openPipeline();
        if (!is_null($time)) {
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_SCHEDULED);
            $this->getHash()->setField(self::FIELD_SCHEDULE_TIME, $time->format(\DateTime::ISO8601));
            $this->getQueue()->getScheduledSet()->addItem($this->getId());
            $this->getQueue()->getScheduledSortedSet()->addItem($time->getTimestamp(), json_encode([$this->getQueueName(), $this->getId()]));
            $this->info(sprintf('Job %s rescheduled in queue for %s', $this->getId(), $time->format(\DateTime::ISO8601)));
        } else {
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_WAITING);
            $this->getQueue()->getWaitingSet()->addItem($this->getId());
            $this->getQueue()->getWaitingList()->pushItem($this->getId());
            $this->info('Job requeued');
        }
        $this->getQueue()->getCancelledSet()->deleteItem($this->getId());
        $this->getQueue()->getFailedSet()->deleteItem($this->getId());
        $this->getQueue()->getCompletedSet()->deleteItem($this->getId());
        $this->getRedis()->closePipeline();

        $this->getEventDispatcher()->dispatch(self::EVENT_REQUEUE, new JobEvent($this));

        return true;
    }

    /**
     * Mark the job as delivered. This is primarily used for testing. In practive, the pump
     * handles setting these fields and events in an atomic way when it delivers a job.
     *
     * @return boolean
     */
    public function deliver()
    {
        if ($this->getQueue()->getWaitingSet()->moveTo($this->getQueue()->getRunningSet(), $this->getId())) {
            $this->getRedis()->openPipeline();
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_DELIVERED);
            $this->getHash()->setField(self::FIELD_DELIVER_TIME, (new \DateTime())->format(\DateTime::ISO8601));
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_DELIVER, new JobEvent($this));
            $this->info('Job delivered');

            return true;
        }

        $this->error('Job delivery failed');

        throw new RocketException('Job delivery failed');
    }

    /**
     * Mark the job as started. This is used by worker when a job is successfully recieved.
     * Also checks that the worker was correctly assigned to the job. Will try for $timeout seconds.
     *
     * @param string $workerName
     * @param int    $timeout
     *
     * @return boolean
     */
    public function start($workerName, $timeout)
    {
        if (!$this->getHash()->exists()) {
            $this->warning(sprintf('Job started by %s does not exist', $workerName));
            throw new RocketException(sprintf('Job started by %s does not exist', $workerName));
        }

        $this->getHash()->clearCache();

        if ($this->getStatus() !== self::STATUS_DELIVERED) {
            $this->warning('Could not start job because it has not been delivered');
            throw new RocketException('Could not start job because it has not been delivered');
        }

        $this->getRedis()->openPipeline();
        $this->getHash()->setField(self::FIELD_WORKER_NAME, $workerName);
        $this->getHash()->setField(self::FIELD_STATUS,     self::STATUS_RUNNING);
        $this->getHash()->setField(self::FIELD_START_TIME, (new \DateTime())->format(\DateTime::ISO8601));
        $this->getHash()->incField(self::FIELD_ATTEMPTS);
        $this->getRedis()->closePipeline($timeout);

        if (($other = $this->getHash()->getField(self::FIELD_WORKER_NAME, true)) !== $workerName) {
            $this->warning(sprintf('Job started by worker %s already started by %s', $workerName, $other));
            throw new RocketException(sprintf('Job started by worker %s already started by %s', $workerName, $other));
        }

        $this->getEventDispatcher()->dispatch(self::EVENT_START, new JobEvent($this));
        $this->info(sprintf('Job started by worker %s', $workerName));

        return true;
    }

    /**
     * Set the progress of a running job. Used by the worker.
     */
    public function progress($progress)
    {
        $this->getHash()->setField(self::FIELD_PROGRESS, $progress);
        $this->getEventDispatcher()->dispatch(self::EVENT_PROGRESS, new JobEvent($this));

        return true;
    }

    /**
     * Mark the job completed. Used by the worker. The job will be deleted after
     * the expiration time by the monitor. Will try for $timeout seconds.
     *
     * @param int $timeout
     *
     * @return boolean
     */
    public function complete($timeout)
    {
        if ($this->getStatus() !== self::STATUS_RUNNING) {
            $this->warning('Could not complete job because it is not running');
            throw new RocketException('Could not complete job because it is not running');
        }

        if ($this->getQueue()->getRunningSet()->moveTo($this->getQueue()->getCompletedSet(), $this->getId())) {
            $this->getRedis()->openPipeline();
            $this->getHash()->setField(self::FIELD_STATUS, self::STATUS_COMPLETED);
            $this->getHash()->setField(self::FIELD_COMPLETE_TIME, (new \DateTime())->format(\DateTime::ISO8601));
            $this->getHash()->deleteField(self::FIELD_WORKER_NAME);
            $this->getRedis()->closePipeline($timeout);

            $this->getEventDispatcher()->dispatch(self::EVENT_COMPLETE, new JobEvent($this));
            $this->info('Job completed');

            return true;
        }

        $this->error('Job complete failed');

        throw new RocketException('Job complete failed');
    }

    /**
     * Mark the job failed. Used by the worker. The job will be deleted after
     * the expiration time by the monitor. Will try for $timeout seconds.
     * Must provide a message explaining why the failure occured.
     *
     * @param int    $timeout
     * @param string $failureMessage
     *
     * @return boolean
     */
    public function fail($timeout, $failureMessage)
    {
        if ($this->getStatus() !== self::STATUS_RUNNING && $this->getStatus() !== self::STATUS_PAUSED) {
            $this->warning('Could not fail job because it is not running or paused');
            throw new RocketException('Could not fail job because it is not running or paused');
        }

        if ($this->getQueue()->getRunningSet()->moveTo($this->getQueue()->getFailedSet(), $this->getId())) {
            $this->getRedis()->openPipeline();
            $this->getHash()->setField(self::FIELD_STATUS,    self::STATUS_FAILED);
            $this->getHash()->setField(self::FIELD_FAIL_TIME, (new \DateTime())->format(\DateTime::ISO8601));
            $this->getHash()->setField(self::FIELD_FAILURE_MSG, $failureMessage);
            $this->getHash()->deleteField(self::FIELD_WORKER_NAME);
            $this->getRedis()->closePipeline($timeout);

            $this->getEventDispatcher()->dispatch(self::EVENT_FAIL, new JobEvent($this));
            $this->info('Job failed');

            return true;
        }

        $this->error('Job failure failed');

        throw new RocketException('Job failure failed');
    }
}
