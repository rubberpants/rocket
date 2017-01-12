<?php

namespace Rocket\Plugin\Statistics;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Plugin\Monitor\MonitorPlugin;
use Rocket\Job\JobEvent;
use Rocket\Job\Job;
use Rocket\Queue\Queue;
use Rocket\Queue\QueueFullEvent;
use Rocket\Queue\QueueInterface;

class StatisticsPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;

    const FIELD_SCHEDULED        = 'scheduled';
    const FIELD_QUEUED           = 'queued';
    const FIELD_CANCELLED        = 'cancelled';
    const FIELD_DELIVERED        = 'delivered';
    const FIELD_REQUEUED         = 'requeued';
    const FIELD_STARTED          = 'started';
    const FIELD_COMPLETED        = 'completed';
    const FIELD_FAILED           = 'failed';
    const FIELD_DELETED          = 'deleted';
    const FIELD_WAITING_ALERTS   = 'waiting_alerts';
    const FIELD_DELIVERED_ALERTS = 'delivered_alerts';
    const FIELD_RUNNING_ALERTS   = 'running_alerts';
    const FIELD_FULL_ALERTS      = 'full_alerts';
    const FIELD_RUN_SECONDS      = 'run_seconds';

    protected $periodSize;
    protected $periodCount;

    public function register()
    {
        $this->periodSize = $this->getConfig()->getStatisticsPeriodSize();
        $this->periodCount = $this->getConfig()->getStatisticsPeriodCount();

        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_SCHEDULED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_QUEUED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_CANCEL, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_CANCELLED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_DELIVER, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_DELIVERED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_REQUEUE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_REQUEUED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_START, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_STARTED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_COMPLETED
            );
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_RUN_SECONDS,
                $this->calculateRunSeconds($event->getJob())
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_FAILED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_DELETE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getGroupName(),
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_DELETED
            );
        });

        $this->getEventDispatcher()->addListener(MonitorPlugin::EVENT_JOB_ALERT, function (JobEvent $event) {
            switch ($event->getJob()->getStatus()) {
                case Job::STATUS_WAITING:
                    $this->incrementStats(
                        $event->getJob()->getGroupName(),
                        $event->getJob()->getQueueName(),
                        $event->getJob()->getType(),
                        self::FIELD_WAITING_ALERTS
                    );
                    break;
                case Job::STATUS_DELIVERED:
                    $this->incrementStats(
                        $event->getJob()->getGroupName(),
                        $event->getJob()->getQueueName(),
                        $event->getJob()->getType(),
                        self::FIELD_DELIVERED_ALERTS
                    );
                    break;
                case Job::STATUS_RUNNING:
                    $this->incrementStats(
                        $event->getJob()->getGroupName(),
                        $event->getJob()->getQueueName(),
                        $event->getJob()->getType(),
                        self::FIELD_RUNNING_ALERTS
                    );
                    break;
            }
        });

        $this->getEventDispatcher()->addListener(Queue::EVENT_FULL, function (QueueFullEvent $event) {
            $this->incrementStats(
                null,
                $event->getQueue()->getQueueName(),
                null,
                self::FIELD_FULL_ALERTS
            );
        });
    }

    public function incrementStats($groupName, $queueName, $jobType, $field, $inc = 1)
    {
        if (!$this->periodSize || !$this->periodCount) {
            return;
        }

        $period = $this->getCurrentPeriodStart();
        $expirationTime = $period+($this->periodSize*$this->periodCount);

        $this->getRedis()->openPipeline();

        $this->getAllStatsHash($period)->incField($field, $inc);
        $this->getAllStatsHash($period)->expireAt($expirationTime);

        if ($groupName) {
            $this->getGroupStatsHash($groupName, $period)->incField($field, $inc);
            $this->getGroupStatsHash($groupName, $period)->expireAt($expirationTime);
        }

        if ($queueName) {
            $this->getQueueStatsHash($queueName, $period)->incField($field, $inc);
            $this->getQueueStatsHash($queueName, $period)->expireAt($expirationTime);
        }

        if ($jobType) {
            $this->getJobTypeStatsHash($jobType, $period)->incField($field, $inc);
            $this->getJobTypeStatsHash($jobType, $period)->expireAt($expirationTime);
        }

        $this->getRedis()->closePipeline();
    }

    public function getAllStatsHash($period)
    {
        $key = sprintf('STATS_ALL:%d', $period);

        return $this->getCachedObject('all', $key, function ($key) {
            $hash = $this->getRedis()->getHashType($key);

            return $hash;
        }, $this->periodCount);
    }

    public function getGroupStatsHash($groupName, $period)
    {
        $key = sprintf('STATS_GROUP:{%s}:%d', $groupName, $period);

        return $this->getCachedObject('group', $key, function ($key) {
            $hash = $this->getRedis()->getHashType($key);

            return $hash;
        }, $this->periodCount);
    }

    public function getQueueStatsHash($queueName, $period)
    {
        $key = sprintf('STATS_QUEUE:{%s}:%d', $queueName, $period);

        return $this->getCachedObject('queue', $key, function ($key) {
            $hash = $this->getRedis()->getHashType($key);

            return $hash;
        }, $this->periodCount);
    }

    public function getJobTypeStatsHash($jobType, $period)
    {
        $key = sprintf('STATS_TYPE:%s:%d', $jobType, $period);

        return $this->getCachedObject('type', $key, function ($key) {
            $hash = $this->getRedis()->getHashType($key);

            return $hash;
        }, $this->periodCount);
    }

    public function getCurrentPeriodStart()
    {
        return time() - (time() % $this->periodSize);
    }

    public function getAllPeriods()
    {
        $periods = [];

        $period = $this->getCurrentPeriodStart();

        for ($i = 0; $i<$this->periodCount; $i++) {
            $periods[] = $period;
            $period -= $this->periodSize;
        }

        return $periods;
    }

    public function getPeriods($offset, $limit)
    {
        $all = $this->getAllPeriods();

        if (!$offset) {
            $offset = 0;
        }

        if (!$offset && !$limit) {
            return $all;
        }

        return array_slice($all, $offset, $limit);
    }

    public function sumFields($fields)
    {
        $summed = [];

        foreach ($fields as $fieldList) {
            foreach ($fieldList as $name => $value) {
                $summed[$name] += $value;
            }
        }

        return $summed;
    }

    public function getAllStatistics($offset = null, $limit = null)
    {
        $stats = [];

        foreach ($this->getPeriods($offset, $limit) as $period) {
            $stats[$period] = $this->getAllStatsHash($period)->getFields();
        }

        return $stats;
    }

    public function getGroupStatistics($groupName, $offset = null, $limit = null)
    {
        $stats = [];

        foreach ($this->getPeriods($offset, $limit) as $period) {
            $stats[$period] = $this->getGroupStatsHash($groupName, $period)->getFields();
        }

        return $stats;
    }

    public function getQueueStatistics(QueueInterface $queue, $offset = null, $limit = null)
    {
        $stats = [];

        foreach ($this->getPeriods($offset, $limit) as $period) {
            $stats[$period] = $this->getQueueStatsHash($queue->getQueueName(), $period)->getFields();
        }

        return $stats;
    }

    public function getJobTypeStatistics($jobType, $offset = null, $limit = null)
    {
        $stats = [];

        foreach ($this->getPeriods($offset, $limit) as $period) {
            $stats[$period] = $this->getJobTypeStatsHash($jobType, $period)->getFields();
        }

        return $stats;
    }

    public function calculateRunSeconds(Job $job)
    {
        $startTime = $job->getStartTime();
        $completeTime = $job->getCompleteTime();

        if (!$startTime || !$completeTime) {

            return 0;
        }

        return abs($completeTime->getTimestamp() - $startTime->getTimestamp());
    }
}
