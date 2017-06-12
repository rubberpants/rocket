<?php

namespace Rocket\Plugin\Pump;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\Job;
use Rocket\Job\JobEvent;
use Rocket\Queue\Queue;
use Rocket\Queue\QueueInterface;
use Rocket\Queue\QueueEvent;
use Rocket\Worker\Worker;
use Rocket\Worker\WorkerEvent;
use Predis\Response\ServerException;

class PumpPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;

    protected $readyQueueList;
    protected $expeditedReadyQueueList;
    protected $readyJobLists = [];
    protected $haltProcessingString;
    protected $pumpActivityString;
    protected $stopPumpString;
    protected $scheduledSortedSet;
    protected $busyWorkersSet;

    public function register()
    {
        $jobEventHandler = function (JobEvent $event) {
            $queue = $event->getJob()->getQueue();
            if ($event->getJob()->isExpedited()) {
                $this->getExpeditedReadyQueueList()->pushItem($queue->getQueueName());
            } else {
                $this->getReadyQueueList()->pushItem($queue->getQueueName());
            }
        };

        $jobDoneEventHandler = function (JobEvent $event) {
            $queue = $event->getJob()->getQueue();
            if ($queue->getExpeditedWaitingList()->getLength() > 0) {
                $this->getExpeditedReadyQueueList()->pushItem($queue->getQueueName());   
            }
            $this->getReadyQueueList()->pushItem($queue->getQueueName());
        };

        $queueEventHandler = function (QueueEvent $event) {
            $queue = $event->getQueue();
            if ($queue->getExpeditedWaitingList()->getLength() > 0) {
                $this->getExpeditedReadyQueueList()->pushItem($queue->getQueueName());
            }
            $this->getReadyQueueList()->pushItem($queue->getQueueName());
        };

        $queueDeleteEventHandler = function (QueueEvent $event) {
            $queue = $event->getQueue();
            $this->getExpeditedReadyQueueList()->deleteItem($queue->getQueueName());
            $this->getReadyQueueList()->deleteItem($queue->getQueueName());
        };

        $addBusyWorkerEventHandler = function (WorkerEvent $event) {
            $worker = $event->getWorker();
            $this->getBusyWorkersSet()->addItem($worker->getWorkerName());
        };

        $removeBusyWorkerEventHandler = function (WorkerEvent $event) {
            $worker = $event->getWorker();
            $this->getBusyWorkersSet()->deleteItem($worker->getWorkerName());
        };

        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE,     $jobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_MOVE,      $jobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_UNPARK,    $jobEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_REQUEUE,   $jobEventHandler);

        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE,  $jobDoneEventHandler);
        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL,      $jobDoneEventHandler);

        $this->getEventDispatcher()->addListener(Queue::EVENT_UPDATE,  $queueEventHandler);
        $this->getEventDispatcher()->addListener(Queue::EVENT_RESUME,  $queueEventHandler);

        $this->getEventDispatcher()->addListener(Queue::EVENT_PAUSE,   $queueDeleteEventHandler);
        $this->getEventDispatcher()->addListener(Queue::EVENT_DELETE,  $queueDeleteEventHandler);

        $this->getEventDispatcher()->addListener(Worker::EVENT_JOB_START, $addBusyWorkerEventHandler);
        $this->getEventDispatcher()->addListener(Worker::EVENT_JOB_DONE,  $removeBusyWorkerEventHandler);
        $this->getEventDispatcher()->addListener(Worker::EVENT_DELETE,    $removeBusyWorkerEventHandler);
    }

    public function getReadyQueueList()
    {
        if (is_null($this->readyQueueList)) {
            $this->readyQueueList = $this->getRedis()->getUniqueListType('READY_QUEUE_LIST');
        }

        return $this->readyQueueList;
    }

    public function getExpeditedReadyQueueList()
    {
        if (is_null($this->expeditedReadyQueueList)) {
            $this->expeditedReadyQueueList = $this->getRedis()->getUniqueListType('EXPEDITED_READY_QUEUE_LIST');
        }

        return $this->expeditedReadyQueueList;
    }

    public function getReadyJobList($jobType)
    {
        $key = sprintf('READY_JOBS:%s', $jobType);

        return $this->getCachedObject('ready_list', $key, function ($key) {
            $list = $this->getRedis()->getListType($key);

            return $list;
        });
    }

    public function getHaltProcessingString()
    {
        if (is_null($this->haltProcessingString)) {
            $this->haltProcessingString = $this->getRedis()->getStringType('HALT_PROCESSING');
        }

        return $this->haltProcessingString;
    }

    public function getScheduledSortedSet()
    {
        if (is_null($this->scheduledSortedSet)) {
            $this->scheduledSortedSet = $this->getRedis()->getSortedSetType('SCHEDULED_JOBS');
        }

        return $this->scheduledSortedSet;
    }

    public function getBusyWorkersSet()
    {
        if (is_null($this->busyWorkersSet)) {
            $this->busyWorkersSet = $this->getRedis()->getSetType('BUSY_WORKERS');
        }

        return $this->busyWorkersSet;
    }

    public function haltProcessing()
    {
        $this->getHaltProcessingString()->on();
    }

    public function resumeProcessing()
    {
        $this->getHaltProcessingString()->off();
    }

    public function isHaltProcessing()
    {
        return $this->getHaltProcessingString()->isOn();
    }

    /*
        This method scales the concurrency limit of the queue between
        the configured min and mix limit based on the current total worker
        utilization of the system. So, if the workers are all busy then every
        queue is limited to it's min limit, and visa-versa.
    */
    public function getCurrentRunningLimit(Queue $queue)
    {
        $min = $queue->getMinRunningLimit();
        $max = $queue->getMaxRunningLimit();

        if ($min == $max) {
            return $min;
        }

        $usage = $this->getWorkerUtilization();

        return ceil((1.0 - $usage) * ($max - $min) + $min);
    }

    public function getWorkerUtilization()
    {
        $totalWorkers = 0;

        if ($plugin = $this->getRocket()->getPlugin('aggregate')) {
            $totalWorkers = $plugin->getAllWorkersSet()->getCount();
        }

        if ($totalWorkers <= 0) {
            return 1.0;
        }

        $percent = $this->getBusyWorkersSet()->getCount() / $totalWorkers;

        $percent = $percent < 0.0 ? 0.0 : $percent;
        $percent = $percent > 1.0 ? 1.0 : $percent;

        return $percent;
    }

    public function pumpReadyQueue($maxJobsToPump, $timeout)
    {
        $jobsPumped = [];

        if ($this->isHaltProcessing()) {
            sleep($timeout);

            return [];
        }

        if ($this->getConfig()->getExpeditedPumpProbability() >= ((float) mt_rand() / (float) mt_getrandmax())) {
            if ($queueName = $this->getExpeditedReadyQueueList()->blockAndPopItem($timeout)) {
                if ($queue = $this->getRocket()->getQueue($queueName)) {
                    $jobsPumped += $this->pumpQueue($queue, $maxJobsToPump, true);
                }
            }
        }

        if ($queueName = $this->getReadyQueueList()->blockAndPopItem($timeout)) {
            if ($queue = $this->getRocket()->getQueue($queueName)) {
                $jobsPumped += $this->pumpQueue($queue, $maxJobsToPump);
            }
        }

        return $jobsPumped;
    }

    public function pumpQueue(QueueInterface $queue, $maxJobsToPump, $pumpExpedited = false)
    {
        if ($plugin = $this->getRocket()->getPlugin('groups')) {
            /* NOTE: This concurrency limit is considered 'soft' in that it's not
               processed in an atomic way and race conditions can push it over. */
            if ($plugin->reachedQueueGroupLimit($queue)) {
                $plugin->addQueueToBlockedSet($queue);
                return [];
            }
        }

        $jobsPumped = $this->executePumpLuaScript(
            $queue->getQueueName(),
            $queue->getRunningSet()->getKey(),
            $queue->getWaitingSet()->getKey(),
            $pumpExpedited ? $queue->getExpeditedWaitingList()->getKey() : $queue->getWaitingList()->getKey(),
            $maxJobsToPump,
            $this->getCurrentRunningLimit($queue)
        );

        foreach ((array) $jobsPumped as $jobId) {
            $job = $this->getRocket()->getQueue($queue->getQueueName())->getJob($jobId);
            $readyJobList = $this->getReadyJobList($job->getType());
            $readyJobList->pushItem(sprintf('["%s","%s"]', $queue->getQueueName(), $jobId));
            $this->debug(sprintf('Pumped job %s from queue %s', $jobId, $queue->getQueueName()));
        }

        return $jobsPumped;
    }

    public function queueScheduledJobs($maxJobsToQueue)
    {
        $jobsQueued = [];

        foreach ($this->executeScheduleLuaScript($this->getScheduledSortedSet()->getKey(), time(), $maxJobsToQueue) as $jobInfo) {
            list($jobId, $queueName) = json_decode($jobInfo, true);
            $job = $this->getRocket()->getQueue($queueName)->getJob($jobId);
            if ($job->getHash()->exists()) {
                $this->info(sprintf('Queuing scheduled job %s into %s', $job->getId(), $job->getQueueName()));
                $job->getQueue()->queueJob($job->getJob(), $job->getType(), $job->getId(), $job->getMaxRuntime());
                $this->getScheduledSortedSet()->deleteItem($jobInfo);
                $jobsQueued[] = $jobId;
            } else {
                $this->warning(sprintf('Scheduled job does not exist: %s', $jobInfo));
            }
        }

        return $jobsQueued;
    }

    protected function executeScheduleLuaScript($scheduleSortSetKey, $timestamp, $maxJobsToQueue)
    {
        $script = <<<EOD
local jobs_to_queue = redis.call('zrangebyscore', KEYS[1], 0, ARGV[1], 'LIMIT', 0, ARGV[2])
for key, value in pairs(jobs_to_queue) do
    redis.call('zrem', KEYS[1], value)
end
return jobs_to_queue
EOD;
        try {
            $jobsToQueue = $this->getRedis()->getClient()->evalsha(
                sha1($script),
                1,
                $scheduleSortSetKey,
                $timestamp,
                $maxJobsToQueue
            );
        } catch (ServerException $e) {
            $jobsToQueue = $this->getRedis()->getClient()->eval(
                $script,
                1,
                $scheduleSortSetKey,
                $timestamp,
                $maxJobsToQueue
            );
        }

        return $jobsToQueue;
    }

    protected function executePumpLuaScript($queueName, $runningSetKey, $waitingSetKey, $waitingListKey, $maxJobsToPump, $runningLimit)
    {
        $script = <<<EOD
local jobs_pumped = {}
while table.getn(jobs_pumped) < tonumber(ARGV[1]) and tonumber(redis.call('scard', KEYS[1])) < tonumber(ARGV[2]) and tonumber(redis.call('scard', KEYS[2])) > 0 do
  local job_id = redis.call('lpop', KEYS[3])
  if job_id then
    if redis.call('exists', KEYS[4]..job_id) then
      redis.call('smove', KEYS[2], KEYS[1], job_id)
      redis.call('hset', KEYS[4]..job_id, 'status', 'delivered')
      redis.call('hset', KEYS[4]..job_id, 'deliver_time', ARGV[3])
      table.insert(jobs_pumped, job_id)
    end
  else
    break
  end
end
return jobs_pumped
EOD;

        try {
            $jobsPumped = $this->getRedis()->getClient()->evalsha(
                sha1($script),
                4,
                $runningSetKey,
                $waitingSetKey,
                $waitingListKey,
                sprintf('JOB:{%s}:', $queueName),
                $maxJobsToPump,
                $runningLimit,
                (new \DateTime())->format(\DateTime::ISO8601)
            );
        } catch (ServerException $e) {
            $jobsPumped = $this->getRedis()->getClient()->eval(
                $script,
                4,
                $runningSetKey,
                $waitingSetKey,
                $waitingListKey,
                sprintf('JOB:{%s}:', $queueName),
                $maxJobsToPump,
                $runningLimit,
                (new \DateTime())->format(\DateTime::ISO8601)
            );
        }

        return $jobsPumped;
    }
}
