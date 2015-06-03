<?php

namespace Rocket\Queue;

trait QueueDataStructuresTrait
{
    use \Rocket\Redis\RedisTrait;

    protected $waitingList;
    protected $waitingSet;
    protected $parkedSet;
    protected $runningSet;
    protected $pausedSet;
    protected $cancelledSet;
    protected $failedSet;
    protected $completedSet;
    protected $pausedString;
    protected $disabledString;
    protected $queuesSet;
    protected $scheduledSet;

    public function getWaitingList()
    {
        if (is_null($this->waitingList)) {
            $this->waitingList = $this->getRedis()->getListType(sprintf('QUEUE:%s', $this->getQueueName()));
        }

        return $this->waitingList;
    }

    public function getWaitingSet()
    {
        if (is_null($this->waitingSet)) {
            $this->waitingSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:WAITING', $this->getQueueName()));
        }

        return $this->waitingSet;
    }

    public function getParkedSet()
    {
        if (is_null($this->parkedSet)) {
            $this->parkedSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:PARKED', $this->getQueueName()));
        }

        return $this->parkedSet;
    }

    public function getRunningSet()
    {
        if (is_null($this->runningSet)) {
            $this->runningSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:RUNNING', $this->getQueueName()));
        }

        return $this->runningSet;
    }

    public function getPausedSet()
    {
        if (is_null($this->pausedSet)) {
            $this->pausedSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:PAUSED', $this->getQueueName()));
        }

        return $this->pausedSet;
    }

    public function getCancelledSet()
    {
        if (is_null($this->cancelledSet)) {
            $this->cancelledSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:CANCELLED', $this->getQueueName()));
        }

        return $this->cancelledSet;
    }

    public function getFailedSet()
    {
        if (is_null($this->failedSet)) {
            $this->failedSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:FAILED', $this->getQueueName()));
        }

        return $this->failedSet;
    }

    public function getCompletedSet()
    {
        if (is_null($this->completedSet)) {
            $this->completedSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:COMPLETED', $this->getQueueName()));
        }

        return $this->completedSet;
    }

    public function getPausedString()
    {
        if (is_null($this->pausedString)) {
            $this->pausedString = $this->getRedis()->getStringType(sprintf('QUEUE:%s:IS_PAUSED', $this->getQueueName()));
        }

        return $this->pausedString;
    }

    public function getDisabledString()
    {
        if (is_null($this->disabledString)) {
            $this->disabledString = $this->getRedis()->getStringType(sprintf('QUEUE:%s:IS_DISABLED', $this->getQueueName()));
        }

        return $this->disabledString;
    }

    public function getQueuesSet()
    {
        if (is_null($this->queuesSet)) {
            $this->queuesSet = $this->getRedis()->getSetType('QUEUES');
        }

        return $this->queuesSet;
    }

    public function getScheduledSet()
    {
        if (is_null($this->scheduledSet)) {
            $this->scheduledSet = $this->getRedis()->getSetType(sprintf('QUEUE:%s:SCHEDULED', $this->getQueueName()));
        }

        return $this->scheduledSet;
    }
}
