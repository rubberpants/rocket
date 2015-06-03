<?php

namespace Rocket\Queue;

use Rocket\Job\JobInterface;

interface QueueInterface
{
    public function init();
    public function getQueueName();
    public function moveJob(JobInterface &$job);
    public function queueJob($job, $id = null, $maxRuntime = 0);
    public function getWaitingLimit();
    public function getMinRunningLimit();
    public function getMaxRunningLimit();
    public function disable();
    public function enable();
    public function pause();
    public function resume();
    public function update();
    public function delete();
    public function isPaused();
    public function getJob($jobId);
    public function getWaitingJobs();
    public function getWaitingJobCount();
    public function getWaitingJobsByPage($page, $pageSize);
    public function getRunningJobs();
    public function getRunningJobCount();
    public function getParkedJobs();
    public function getParkedJobCount();
    public function getActiveJobs();
    public function getActiveJobCount();
    public function getCancelledJobs();
    public function getCancelledJobCount();
    public function getCompletedJobs();
    public function getCompletedJobCount();
    public function getFailedJobs();
    public function getFailedJobCount();
    public function getInactiveJobs();
    public function getInactiveJobCount();
    public function getAllJobs();
    public function getAllJobCount();
    public function getJobsByStatus($status);
    public function flushJobsByStatus($status);
}
