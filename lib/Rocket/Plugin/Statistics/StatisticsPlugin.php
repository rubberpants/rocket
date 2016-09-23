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

    protected $periodSize;
    protected $periodCount;

    protected $allStatsHashes   = [];
    protected $queueStatsHashes = [];
    protected $jobTypeStatsHashes  = [];

    public function register()
    {
        $this->periodSize = $this->getConfig()->getStatisticsPeriodSize();
        $this->periodCount = $this->getConfig()->getStatisticsPeriodCount();

        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_SCHEDULED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_QUEUED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_CANCEL, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_CANCELLED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_DELIVER, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_DELIVERED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_REQUEUE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_REQUEUED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_START, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_STARTED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_COMPLETED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_FAILED
            );
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_DELETE, function (JobEvent $event) {
            $this->incrementStats(
                $event->getJob()->getQueueName(),
                $event->getJob()->getType(),
                self::FIELD_DELETED
            );
        });

        $this->getEventDispatcher()->addListener(MonitorPlugin::EVENT_JOB_ALERT, function (JobEvent $event) {
            switch ($event->getJob()->getStatus()) {
                case Job::STATUS_WAITING:
                    $this->incrementStats(
                        $event->getJob()->getQueueName(),
                        $event->getJob()->getType(),
                        self::FIELD_WAITING_ALERTS
                    );
                    break;
                case Job::STATUS_DELIVERED:
                    $this->incrementStats(
                        $event->getJob()->getQueueName(),
                        $event->getJob()->getType(),
                        self::FIELD_DELIVERED_ALERTS
                    );
                    break;
                case Job::STATUS_RUNNING:
                    $this->incrementStats(
                        $event->getJob()->getQueueName(),
                        $event->getJob()->getType(),
                        self::FIELD_RUNNING_ALERTS
                    );
                    break;
            }
        });

        $this->getEventDispatcher()->addListener(Queue::EVENT_FULL, function (QueueFullEvent $event) {
            $this->incrementStats(
                $event->getQueue()->getQueueName(),
                '',
                self::FIELD_FULL_ALERTS
            );
        });
    }

    public function incrementStats($queueName, $jobType, $field)
    {
        if (!$this->periodSize || !$this->periodCount) {
            return;
        }

        $period = $this->getCurrentPeriodStart();
        $expirationTime = $period+($this->periodSize*$this->periodCount);

        $this->getRedis()->openPipeline();

        $this->getAllStatsHash($period)->incField($field);
        $this->getAllStatsHash($period)->expireAt($expirationTime);

        $this->getQueueStatsHash($queueName, $period)->incField($field);
        $this->getQueueStatsHash($queueName, $period)->expireAt($expirationTime);

        $this->getJobTypeStatsHash($jobType, $period)->incField($field);
        $this->getJobTypeStatsHash($jobType, $period)->expireAt($expirationTime);

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

    public function getPeriods($startPeriod = null, $periodCount = null)
    {
        if (is_null($startPeriod)) {
            $startPeriod = 0;
        }

        if (is_null($periodCount)) {
            $periodCount = $this->periodCount;
        }

        $periods = [];

        $start = $this->getCurrentPeriodStart();

        for ($i = $startPeriod; $i<($startPeriod+$periodCount); $i++) {
            $period = $start + ($i * $this->periodSize);
            $periods[] = $period;
        }

        return $periods;
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

    public function getAllStatistics($startPeriod = null, $periodCount = null)
    {
        $stats = [];

        foreach ($this->getPeriods($startPeriod, $periodCount) as $period) {
            $stats[$period] = $this->getAllStatsHash($period)->getFields();
        }

        return $stats;
    }

    public function getQueueStatistics(QueueInterface $queue, $startPeriod = null, $periodCount = null)
    {
        $stats = [];

        foreach ($this->getPeriods($startPeriod, $periodCount) as $period) {
            $stats[$period] = $this->getQueueStatsHash($queue->getQueueName(), $period)->getFields();
        }

        return $stats;
    }

    public function getJobTypeStatistics($jobType, $startPeriod = null, $periodCount = null)
    {
        $stats = [];

        foreach ($this->getPeriods($startPeriod, $periodCount) as $period) {
            $stats[$period] = $this->getJobTypeStatsHash($jobType, $period)->getFields();
        }

        return $stats;
    }
}
