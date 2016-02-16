<?php

namespace Rocket\Plugin\QueueGroups;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Job\JobEvent;
use Rocket\Job\JobInterface;
use Rocket\Job\Job;
use Rocket\Queue\Queue;
use Rocket\Queue\QueueEvent;

class QueueGroupsPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;
    use \Rocket\JqTrait;

    protected $queueGroupSets = [];

    public function register()
    {
        $this->getEventDispatcher()->addListener(Job::EVENT_SCHEDULE, function (JobEvent $event) {
            $this->addQueueGroupFromJob($event->getJob(), $event->getJob()->getQueueName());
        });

        $this->getEventDispatcher()->addListener(Job::EVENT_QUEUE, function (JobEvent $event) {
            $this->addQueueGroupFromJob($event->getJob(), $event->getJob()->getQueueName());
        });

        $this->getEventDispatcher()->addListener(Queue::EVENT_DELETE, function (QueueEvent $event) {
            foreach ((array) $this->getGroups() as $group) {
                $this->getGroupQueuesSet($group)->deleteItem($event->getQueue()->getQueueName());
                if (!$this->getGroupQueuesSet($group)->exists()) {
                    $this->getAllGroupsSet()->deleteItem($group);
                }
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

    public function getAllGroupsSet()
    {
        return $this->getRedis()->getSetType('ALL_GROUPS');
    }

    public function getGroupFromJob(JobInterface $job)
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

    public function addQueueGroupFromJob(JobInterface $job, $queueName)
    {
        if (!is_null($group = $this->getGroupFromJob($job))) {
            $this->getGroupQueuesSet($group)->addItem($queueName);
            $this->getAllGroupsSet()->addItem($group);
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
}
