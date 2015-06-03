<?php

namespace Rocket\Job;

interface JobInterface
{
    public function getId();
    public function getStatus();
    public function getProgress();
    public function getJob();
    public function getQueue();
    public function getQueueName();
    public function getWorkerName();
    public function getQueueTime();
    public function getDeliverTime();
    public function getStartTime();
    public function getCompleteTime();
    public function getFailTime();
    public function getCancelTime();
    public function getHistory();
    public function shiftBefore($pivot);
    public function shiftAfter($pivot);
    public function park();
    public function unpark();
    public function cancel();
    public function pause();
    public function resume();
    public function delete();
    public function requeue();
    public function deliver();
    public function start($workerName, $timeout);
    public function progress($progress);
    public function complete($timeout);
    public function fail($timeout, $failureMessage);
    public function getFailureMessage();
    public function getAlertMessage();
}
