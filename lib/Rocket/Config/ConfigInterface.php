<?php

namespace Rocket\Config;

interface ConfigInterface
{
    public function getApplicationName();
    public function getRedisConnections();
    public function getDefaultQueueName();
    public function getQueuesDefaultWaitingLimit();
    public function getQueuesWaitingLimit($queueName);
    public function getQueuesDefaultMinRunningLimit();
    public function getQueuesMinRunningLimit($queueName);
    public function getQueuesDefaultMaxRunningLimit();
    public function getQueuesMaxRunningLimit($queueName);
    public function getStatisticsPeriodSize();
    public function getStatisticsPeriodCount();
    public function getMonitorWaitingJobMax();
    public function getMonitorDeliveredJobMax();
    public function getMonitorDefaultRunningJobMax();
    public function getMonitorCompletedJobTTL();
    public function getMonitorFailedJobTTL();
    public function getMonitorCancelledJobTTL();
    public function getRouterDefaultExpr();
    public function getRouterRules();
    public function getWorkerJobWaitTimeout();
    public function getWorkerMaxQueuesToPump();
    public function getWorkerMaxJobsToPump();
    public function getWorkerMaxEventsToHandle();
    public function getWorkerCommandTTL();
    public function getWorkerResolveTimeout();
    public function getWorkerMaxInactivity();
    public function getTotalWorkerCount();
    public function getTopCacheTTL();
}