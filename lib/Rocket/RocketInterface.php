<?php

namespace Rocket;

interface RocketInterface
{
    public function performOverheadTasks($interval = 1);
    public function getQueue($queueName, $maxCache = 16);
    public function getQueues();
    public function getQueueCount();
    public function getJob($jobId, $queueName = null, $maxCache = 16);
    public function getPlugin($name);
    public function getWorker($workerName, $maxCache = 16);
    public function getQueueNamebyJobId($jobId);
    public function getJobsQueueHash();
}
