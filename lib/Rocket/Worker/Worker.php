<?php

namespace Rocket\Worker;

use Rocket\RocketException;
use Rocket\RocketInterface;
use Rocket\Job\Job;
use Rocket\Job\JobEvent;

class Worker implements WorkerInterface
{
    use \Rocket\ObjectCacheTrait;
    use \Rocket\LogTrait;
    use \Rocket\Config\ConfigTrait;
    use \Rocket\Plugin\EventTrait;
    use \Rocket\Redis\RedisTrait;

    const EVENT_ACTIVITY   = 'worker.activity';
    const EVENT_OVERHEAD   = 'worker.overhead';
    const EVENT_JOB_START  = 'worker.job_start';
    const EVENT_JOB_DONE   = 'worker.job_done';
    const EVENT_JOB_PAUSE  = 'worker.job_pause';
    const EVENT_JOB_RESUME = 'worker.job_resume';
    const EVENT_DELETE     = 'worker.delete';

    const FIELD_LAST_ACTIVITY   = 'last_activity';
    const FIELD_INFO            = 'info';
    const FIELD_FLAG            = 'flag';
    const FIELD_COMMAND         = 'command';
    const FIELD_COMMAND_TIME    = 'command_time';
    const FIELD_CURRENT_JOB     = 'current_job';
    const FIELD_CURRENT_QUEUE   = 'current_queue';
    const FIELD_JOBS_DELIVERED  = 'jobs_delivered';
    const FIELD_JOBS_STARTED    = 'jobs_started';
    const FIELD_JOBS_COMPLETED  = 'jobs_completed';
    const FIELD_JOBS_FAILED     = 'jobs_failed';
    const FIELD_OVERHEAD_COUNT  = 'overhead_count';
    const FIELD_LAST_JOB_START  = 'last_job_start';
    const FIELD_LAST_JOB_DONE   = 'last_job_done';
    const FIELD_TOTAL_TIME_IDLE = 'total_time_idle';
    const FIELD_TOTAL_TIME_BUSY = 'total_time_busy';

    const FLAG_PAUSE  = 'pause';
    const FLAG_RESUME = 'resume';
    const FLAG_STOP   = 'stop';

    protected $rocket;
    protected $workerName;
    protected $pump;
    protected $monitor;
    protected $currentJob;
    protected $hash;

    public function __construct(RocketInterface $rocket, $workerName)
    {
        $this->rocket = $rocket;
        $this->workerName = $workerName;
        $this->setLogContext('worker', $workerName);
        $this->pump = $rocket->getPlugin('pump');
        $this->monitor = $rocket->getPlugin('monitor');
    }

    /**
     * Get the name of the worker.
     *
     * @return string
     */
    public function getWorkerName()
    {
        return $this->workerName;
    }

    /**
     * Get the hash that contains the worker information.
     *
     * @return HashType
     */
    public function getHash()
    {
        if (is_null($this->hash)) {
            $this->hash = $this->getRedis()->getHashType(sprintf('WORKER:%s', $this->getWorkerName()));
        }

        return $this->hash;
    }

    /**
     * Get the id of the current job
     * Available if getNewJob returns true.
     *
     * @return string
     */
    public function getCurrentJobId()
    {
        return $this->getHash()->getField(self::FIELD_CURRENT_JOB);
    }

    /**
     * Get the name of the queue the current job resides in
     * Available if getNewJob returns true.
     *
     * @return string
     */
    public function getCurrentQueueName()
    {
        return $this->getHash()->getField(self::FIELD_CURRENT_QUEUE);
    }

    /**
     * Get the current job.
     *
     * @return Job
     */
    public function getCurrentJob()
    {
        if (is_null($this->currentJob)) {
            if ($this->getHash()->getField(self::FIELD_CURRENT_JOB) && $this->getHash()->getField(self::FIELD_CURRENT_QUEUE)) {
                $this->currentJob = $this->rocket->getJob(
                    $this->getHash()->getField(self::FIELD_CURRENT_JOB),
                    $this->getHash()->getField(self::FIELD_CURRENT_QUEUE)
                );
            } else {
                throw new RocketException('This worker does not have a current job');
            }
        }

        return $this->currentJob;
    }

    /**
     * Get the total number of jobs delivered to this worker.
     *
     * @return integer
     */
    public function getJobsDelivered()
    {
        return $this->getHash()->getField(self::FIELD_JOBS_DELIVERED);
    }

    /**
     * Get the total number of jobs started by this worker.
     *
     * @return integer
     */
    public function getJobsStarted()
    {
        return $this->getHash()->getField(self::FIELD_JOBS_STARTED);
    }

    /**
     * Get the total number of jobs completed by this worker.
     *
     * @return integer
     */
    public function getJobsCompleted()
    {
        return $this->getHash()->getField(self::FIELD_JOBS_COMPLETED);
    }

    /**
     * Get the total number of jobs failed by this worker.
     *
     * @return integer
     */
    public function getJobsFailed()
    {
        return $this->getHash()->getField(self::FIELD_JOBS_FAILED);
    }

    /**
     * Get the total number of time this worker has performed overhead tasks.
     *
     * @return integer
     */
    public function getOverheadCount()
    {
        return $this->getHash()->getField(self::FIELD_OVERHEAD_COUNT);
    }

    /**
     * Get the timestamp of the last started job.
     *
     * @return integer
     */
    public function getLastJobStart()
    {
        return $this->getHash()->getField(self::FIELD_LAST_JOB_START);
    }

    /**
     * Get the timestamp of the last job completed.
     *
     * @return integer
     */
    public function getLastJobDone()
    {
        return $this->getHash()->getField(self::FIELD_LAST_JOB_DONE);
    }

    /**
     * Get the total number of seconds this worker has spent not working on a job.
     *
     * @return integer
     */
    public function getTotalTimeIdle()
    {
        return $this->getHash()->getField(self::FIELD_TOTAL_TIME_IDLE);
    }

    /**
     * Get the total number of seconds this worker has spent working on a job.
     *
     * @return integer
     */
    public function getTotalTimeBusy()
    {
        return $this->getHash()->getField(self::FIELD_TOTAL_TIME_BUSY);
    }

    /**
     * Set a command to send to the worker. The worker will recieve the command
     * the next time it calls getNewJob.
     *
     * @param string $command
     *
     * @return Worker
     */
    public function setCommand($command)
    {
        $this->getRedis()->openPipeline();
        $this->getHash()->setField(self::FIELD_COMMAND, $command);
        $this->getHash()->setField(self::FIELD_COMMAND_TIME, time()); //integer for ease of subtraction later
        $this->getRedis()->closePipeline();

        return $this;
    }

    /**
     * Clear the command for the worker.
     *
     * @return Worker
     */
    public function clearCommand()
    {
        $this->getRedis()->openPipeline();
        $this->getHash()->deleteField(self::FIELD_COMMAND);
        $this->getHash()->deleteField(self::FIELD_COMMAND_TIME);
        $this->getRedis()->closePipeline();

        return $this;
    }

    /**
     * Get any info that was set by the worker when calling getNewJob.
     *
     * @return string
     */
    public function getInfo()
    {
        return $this->getHash()->getField(self::FIELD_INFO);
    }

    /**
     * Perform some overhaead tasks, then wait to recieve a job of the specified type.
     * If no job of the specified type was available, returns false. If a command is waiting the worker a WorkerCommandException
     * will be thrown. Optionally specify a string of info to set for the worker. Optionally set the probability
     * that the worker will perform some overhead tasks before getting a job. The default is 1.0, always do overhead tasks.
     *
     * If returns true, then getCurrentJob() will have the job object that was recieved.
     *
     * @param string $jobType
     * @param string $workerInfo
     * @param float  $overheadProbability
     *
     * @throws WorkerCommandException
     *
     * @return boolean
     */
    public function getNewJob($jobType = 'default', $workerInfo = null, $overheadProbability = 1.0, $timeout = null)
    {
        $this->activity();

        if (!is_null($workerInfo)) {
            $this->getHash()->setField(self::FIELD_INFO, $workerInfo);
            $this->debug('Worker info sent: '.$workerInfo);
        }

        if ($command = $this->getHash()->getField(self::FIELD_COMMAND, true)) {
            $valid = (time()-$this->getHash()->getField(self::FIELD_COMMAND_TIME) < $this->getConfig()->getWorkerCommandTTL());
            $this->clearCommand();
            if ($valid) {
                $this->debug('Worker command recieved: '.$command);
                throw new WorkerCommandException($command);
            }
        }

        if ($jobId = $this->getHash()->getField(self::FIELD_CURRENT_JOB)) {
            $this->warning(sprintf('Worker already working on job %s', $jobId));

            return true;
        }

        $timeout = $timeout ?: $this->getConfig()->getWorkerJobWaitTimeout();

        if ($this->randomFloat() <= $overheadProbability) {
            $this->performOverheadTasks();
            $this->getHash()->incField(self::FIELD_OVERHEAD_COUNT);
        }

        $job = $this->pump->getReadyJobList($jobType)->blockAndPopItem($timeout);

        if ($job === null) {
            $this->debug('Worker timed out waiting for job');

            return false;
        }

        list($queueName, $jobId) = json_decode($job, true);

        if ($this->currentJob = $this->rocket->getJob($jobId, $queueName)) {
            $this->currentJob->getHash()->clearCache();
            $this->getRedis()->openPipeline();
            $this->currentJob->getHash()->setField(Job::FIELD_WORKER_NAME, $this->getWorkerName());
            $this->getHash()->setField(self::FIELD_CURRENT_JOB, $jobId);
            $this->getHash()->setField(self::FIELD_CURRENT_QUEUE, $queueName);
            $this->getHash()->incField(self::FIELD_JOBS_DELIVERED);
            $this->getHash()->deleteField(self::FIELD_FLAG);
            $this->getRedis()->closePipeline();
            $this->getEventDispatcher()->dispatch(Job::EVENT_DELIVER, new JobEvent($this->currentJob));
            $this->debug('Worker delivered job '.$jobId.' from queue '.$queueName);

            return true;
        }

        $this->warning(sprintf('Job %s delivered to worker does not exist', $job));

        throw new RocketException(sprintf('Job %s delivered to worker does not exist', $job));
    }

    /**
     * Tell the system we've recieved the job and are now working on it.
     *
     * @return boolean
     */
    public function startCurrentJob()
    {
        $this->activity();

        if ($this->getCurrentJob()->start($this->getWorkerName(), $this->getConfig()->getWorkerResolveTimeout())) {
            $this->getRedis()->openPipeline();
            $this->getHash()->incField(self::FIELD_JOBS_STARTED);
            $this->getHash()->setField(self::FIELD_LAST_JOB_START, time()); //integer for ease of subtraction later
            $lastDone = $this->getHash()->getField(self::FIELD_LAST_JOB_DONE) ?: time();
            $this->getHash()->incField(self::FIELD_TOTAL_TIME_IDLE, time() - $lastDone);
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_JOB_START, new WorkerEvent($this));

            return true;
        }

        return false;
    }

    /**
     * Tell the system we've made progress on the job. If the job is failed to be stopped a WorkerStopException will
     * be thrown and the worker should stop working and then fail the job.  If a WorkerPauseException is thrown,
     * the worker should pause the job, and keep updating until either a WorkerResumeException or WorkerStopException
     * is thrown.
     *
     * @param integer $progress
     *
     * @throws WorkerPauseException
     * @throws WorkerResumeException
     * @throws WorkerStopException
     *
     * @return boolean
     */
    public function progressCurrentJob($progress)
    {
        $this->activity();

        $this->getCurrentJob()->progress($progress);

        if ($flag = $this->getHash()->getField(self::FIELD_FLAG)) {
            $this->getHash()->deleteField(self::FIELD_FLAG);
            switch ($flag) {
                case self::FLAG_PAUSE:  throw new WorkerPauseException();
                case self::FLAG_RESUME: throw new WorkerResumeException();
                case self::FLAG_STOP:   throw new WorkerStopException();
            }
        }
    }

    /**
     * Signal the worker to pause the running job.
     *
     * @return boolean
     */
    public function pauseCurrentJob()
    {
        if ($this->getCurrentJob()->getStatus() != Job::STATUS_RUNNING) {
            $this->warning('Cannot pause job because it is not running');

            return false;
        }

        $this->getHash()->setField(self::FIELD_FLAG, self::FLAG_PAUSE);
        $this->getEventDispatcher()->dispatch(self::EVENT_JOB_PAUSE, new WorkerEvent($this));

        return true;
    }

    /**
     * Signal the worker to resume a paused job.
     *
     * @return boolean
     */
    public function resumeCurrentJob()
    {
        /* If the job was flagged to be paused but the worker didn't actually
           do it yet then just pretend it never happened. */
        if ($this->getCurrentJob()->getStatus() == Job::STATUS_RUNNING) {
            if ($this->getHash()->deleteField(self::FIELD_FLAG)) {
                return true;
            }
        }

        if ($this->getCurrentJob()->getStatus() != Job::STATUS_PAUSED) {
            $this->warning('Cannot resume job because it is not paused');

            throw new RocketException('Cannot resume job because it is not paused');
        }

        $this->getHash()->setField(self::FIELD_FLAG, self::FLAG_RESUME);
        $this->getEventDispatcher()->dispatch(self::EVENT_JOB_RESUME, new WorkerEvent($this));

        return true;
    }

    /**
     * Signal the worker to stop a running job. The worker will mark the job failed.
     *
     * @return boolean
     */
    public function stopCurrentJob()
    {
        if ($this->getCurrentJob()->getStatus() != Job::STATUS_RUNNING && $this->getCurrentJob()->getStatus() != Job::STATUS_PAUSED) {
            $this->warning('Cannot stop job because it is not running or paused');

            throw new RocketException('Cannot stop job because it is not running or paused');
        }

        $this->getHash()->setField(self::FIELD_FLAG, self::FLAG_STOP);

        return true;
    }

    /**
     * Tell the system we've recieved the job and are now working on it.
     *
     * @return boolean
     */
    public function completeCurrentJob()
    {
        $this->activity();

        if ($this->getCurrentJob()->complete($this->getConfig()->getWorkerResolveTimeout())) {
            $this->getRedis()->openPipeline();
            $this->getHash()->incField(self::FIELD_JOBS_COMPLETED);
            $this->getHash()->setField(self::FIELD_LAST_JOB_DONE, time()); //integer for ease of subtraction later
            $this->getHash()->incField(self::FIELD_TOTAL_TIME_BUSY, time() - $this->getHash()->getField(self::FIELD_LAST_JOB_START));
            $this->getHash()->deleteField(self::FIELD_CURRENT_JOB);
            $this->getHash()->deleteField(self::FIELD_CURRENT_QUEUE);
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_JOB_DONE, new WorkerEvent($this));

            $this->getHash()->clearCache();
            $this->currentJob = null;

            return true;
        }

        return false;
    }

    /**
     * Tell the system we're failing the job.
     *
     * @param string $failureMessage
     *
     * @return boolean
     */
    public function failCurrentJob($failureMessage)
    {
        $this->activity();

        if ($this->getCurrentJob()->fail($this->getConfig()->getWorkerResolveTimeout(), $failureMessage)) {
            $this->getRedis()->openPipeline();
            $this->getHash()->incField(self::FIELD_JOBS_FAILED);
            $this->getHash()->setField(self::FIELD_LAST_JOB_DONE, time()); //integer for ease of subtraction later
            $this->getHash()->incField(self::FIELD_TOTAL_TIME_BUSY, time() - $this->getHash()->getField(self::FIELD_LAST_JOB_START));
            $this->getHash()->deleteField(self::FIELD_CURRENT_JOB);
            $this->getHash()->deleteField(self::FIELD_CURRENT_QUEUE);
            $this->getRedis()->closePipeline();

            $this->getEventDispatcher()->dispatch(self::EVENT_JOB_DONE, new WorkerEvent($this));

            $this->getHash()->clearCache();
            $this->currentJob = null;

            return true;
        }

        return false;
    }

    /**
     * Perform some overhead tasks.
     */
    public function performOverheadTasks()
    {
        $this->getEventDispatcher()->dispatch(self::EVENT_OVERHEAD, new WorkerEvent($this));
        $this->pump->execute($this->getConfig()->getWorkerMaxQueuesToPump(), $this->getConfig()->getWorkerMaxJobsToPump(), $this->getConfig()->getWorkerMaxSchedJobsToQueue());
        $this->monitor->execute($this->getConfig()->getWorkerMaxEventsToHandle());
        /* Add addtl. overhead tasks here */
    }

    /**
     * Reset the tracking statistics for this worker.
     */
    public function resetStats()
    {
        $this->activity();
        $this->getRedis()->openPipeline();
        $this->getHash()->deleteField(self::FIELD_JOBS_DELIVERED);
        $this->getHash()->deleteField(self::FIELD_JOBS_STARTED);
        $this->getHash()->deleteField(self::FIELD_JOBS_COMPLETED);
        $this->getHash()->deleteField(self::FIELD_JOBS_FAILED);
        $this->getHash()->deleteField(self::FIELD_OVERHEAD_COUNT);
        $this->getHash()->deleteField(self::FIELD_LAST_JOB_START);
        $this->getHash()->deleteField(self::FIELD_LAST_JOB_DONE);
        $this->getHash()->deleteField(self::FIELD_TOTAL_TIME_IDLE);
        $this->getHash()->deleteField(self::FIELD_TOTAL_TIME_BUSY);
        $this->getRedis()->closePipeline();
    }

    /**
     * Delete all information about this worker.
     */
    public function delete()
    {
        $this->getEventDispatcher()->dispatch(self::EVENT_DELETE, new WorkerEvent($this));
        $this->getRedis()->openPipeline();
        $this->getHash()->delete();
        $this->getRedis()->closePipeline();
    }

    /**
     * Return a random floating point number between $min and $max.
     *
     * @param float $min
     * @param float $max
     *
     * @return float
     */
    protected function randomFloat($min = 0, $max = 1)
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    /**
     * Signals the system that this worker was active.
     */
    protected function activity()
    {
        $this->getRedis()->openPipeline();
        $this->getHash()->setField(self::FIELD_LAST_ACTIVITY, (new \DateTime())->format(\DateTime::ISO8601));
        $this->getHash()->expire($this->getConfig()->getWorkerMaxInactivity());
        $this->getRedis()->closePipeline();
        $this->getEventDispatcher()->dispatch(self::EVENT_ACTIVITY, new WorkerEvent($this));
    }
}
