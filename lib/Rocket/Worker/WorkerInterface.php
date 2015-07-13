<?php

namespace Rocket\Worker;

interface WorkerInterface
{
    public function getWorkerName();
    public function getCurrentJobId();
    public function getCurrentQueueName();
    public function getCurrentJob();
    public function getJobsDelivered();
    public function getJobsStarted();
    public function getJobsCompleted();
    public function getJobsFailed();
    public function getOverheadCount();
    public function getLastJobStart();
    public function getLastJobDone();
    public function getTotalTimeIdle();
    public function getTotalTimeBusy();
    public function setCommand($command);
    public function clearCommand();
    public function getInfo();
    public function getNewJob($jobType = 'default', $workerInfo = null, $overhead = 1.0, $timeout = null);
    public function startCurrentJob();
    public function progressCurrentJob($progress);
    public function pauseCurrentJob();
    public function resumeCurrentJob();
    public function stopCurrentJob();
    public function completeCurrentJob();
    public function failCurrentJob($failureMessage);
    public function performOverheadTasks();
    public function resetStats();
    public function delete();
}
