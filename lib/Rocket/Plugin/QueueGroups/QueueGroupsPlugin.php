<?php

namespace Rocket\Plugin\QueueGroups;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\JobEvent;
use Rocket\Job\Job;
use Rocket\Queue\Queue;
use Rocket\Queue\QueueEvent;
use Rocket\Queue\QueueInterface;

class QueueGroupsPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;
    use \Rocket\JqTrait;

    protected $groupQueuesHash;
    protected $allGroupsSet;

    //Assumption: A queue is a member of a single group

    public function register()
    {
        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, function (JobEvent $event) {
            $this->addQueueGroupFromJob($event->getJob(), $event->getJob()->getQueueName());
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE, function (JobEvent $event) {
            $this->addQueueGroupFromJob($event->getJob(), $event->getJob()->getQueueName());
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_START, function (JobEvent $event) {
            $this->getGroupRunningSet($event->getJob()->getGroupName())->addItem($event->getJob()->getId());
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_COMPLETE, function (JobEvent $event) {
            $this->getGroupRunningSet($event->getJob()->getGroupName())->deleteItem($event->getJob()->getId());
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_FAIL, function (JobEvent $event) {
            $this->getGroupRunningSet($event->getJob()->getGroupName())->deleteItem($event->getJob()->getId());
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_DELETE, function (JobEvent $event) {
            $this->getGroupRunningSet($event->getJob()->getGroupName())->deleteItem($event->getJob()->getId());
        });

        $this->getEventDispatcher()->addListener(Queue::EVENT_DELETE, function (QueueEvent $event) {
            $queueName = $event->getQueue()->getQueueName();
            if ($groupName = $this->getGroupForQueue($queueName)) {
                $this->getGroupQueuesSet($groupName)->deleteItem($queueName);
                $this->getGroupQueuesHash()->deleteField($queueName);
            }
        });
    }

    public function getGroupQueuesSet($group)
    {
        $key = sprintf('GROUP_QUEUES:%s', $group);

        return $this->getCachedObject('group_queues', $key, function ($key) {
            $set = $this->getRedis()->getSetType($key);

            return $set;
        });
    }

    public function getGroupRunningSet($group)
    {
        $key = sprintf('GROUP_RUNNING_JOBS:%s', $group);

        return $this->getCachedObject('group_running_jobs', $key, function ($key) {
            $set = $this->getRedis()->getSetType($key);

            return $set;
        });
    }

    public function getAllGroupsSet()
    {
        if (is_null($this->allGroupsSet)) {
            $this->allGroupsSet = $this->getRedis()->getSetType('ALL_GROUPS');
        }

        return $this->allGroupsSet;
    }

    public function getGroupQueuesHash()
    {
        if (is_null($this->groupQueuesHash)) {
            $this->groupQueuesHash = $this->getRedis()->getHashType('GROUP_QUEUES');
        }

        return $this->groupQueuesHash;
    }

    public function getGroupFromJob(Job $job)
    {
        if (!($filter = $this->getConfig()->getQueueGroupExpr())) {
            return;
        }

        try {
            return trim(strtolower($this->executeJq($filter, $job->getJob())));
        } catch (RocketException $e) {
            throw new QueueGroupException($e->getMessage());
        }
    }

    public function addQueueGroupFromJob(Job $job, $queueName)
    {
        if (!is_null($group = $this->getGroupFromJob($job))) {
            $this->getGroupQueuesSet($group)->addItem($queueName);
            $this->getAllGroupsSet()->addItem($group);
            $this->getGroupQueuesHash()->setField($queueName, $group);
            $job->getHash()->setField(Job::FIELD_GROUP_NAME, $group);
        }
    }

    public function getGroups()
    {
        return $this->getAllGroupsSet()->getItems();
    }

    public function getQueuesByGroup($group)
    {
        return $this->getGroupQueuesSet($group)->getItems();
    }

    public function getGroupForQueue($queueName)
    {
        return $this->getGroupQueuesHash()->getField($queueName);
    }

    public function getGroupRunningJobs($group)
    {
        return $this->getGroupRunningSet($group)->getItems();
    }

    public function reachedQueueGroupLimit(QueueInterface $queue)
    {
        if ($defaultLimit = $this->getConfig()->getQueueGroupsDefaultRunningLimit()) {
            $limit = $defaultLimit;
        } else {
            return false;
        }

        if ($groupName = $this->getGroupForQueue($queue->getQueueName())) {
            if ($specificLimit = $this->getConfig()->getQueueGroupsRunningLimit($groupName)) {
                $limit = $specificLimit;
            }
            if ($this->getGroupRunningSet($groupName)->getCount() >= $limit) {
                return true;
            }
        }

        return false;
    }
}
