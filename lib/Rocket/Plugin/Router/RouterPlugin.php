<?php

namespace Rocket\Plugin\Router;

use Rocket\Plugin\AbstractPlugin;
use Rocket\Queue\QueueInterface;
use Symfony\Component\Process\Process;

/*
    See: http://stedolan.github.io/jq/manual/
*/

class RouterPlugin extends AbstractPlugin
{
    protected $routingFilter;
    protected $defaultRule;
    protected $rules;

    public function register()
    {
        $this->rules = [];
        $this->defaultRule = new DefaultRoutingRule();
        $this->defaultRule->setQueueNameExpr($this->getConfig()->getRouterDefaultExpr());

        foreach ((array) $this->getConfig()->getRouterRules() as $config) {
            $rule = new RoutingRule();
            $rule->setFilterExpr($config['filter_expr']);
            $rule->setQueueNameExpr($config['queue_expr']);
            $this->rules[] = $rule;
        }
    }

    public function routeJob($job, DateTime $scheduleTime = null)
    {
        if ($this->scheduleTime) {
            return $this->getRocket()->getQueue($this->applyRulesToJob($job))->scheduleJob($scheduleTime, $job);
        } else {
            return $this->getRocket()->getQueue($this->applyRulesToJob($job))->queueJob($job);
        }
    }

    public function applyRulesToQueue(QueueInterface $sourceQueue, $status = Job::STATUS_WAITING)
    {
        foreach ((array) $sourceQueue->getJobsByStatus($status) as $jobId) {
            $job = $sourceQueue->getJob($jobId);
            $this->getRocket()->getQueue($this->applyRulesToJob($job->getJob()))->moveJob($job);
        }
    }

    public function applyRulesToJob($job)
    {
        return trim(strtolower($this->executeJq($this->getRoutingFilter(), $job)));
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function getDefaultRule()
    {
        return $this->defaultRule;
    }

    public function executeJq($filter, $input)
    {
        $commandLine = sprintf('jq -c %s', escapeshellarg($filter));
        $process = new Process($commandLine, null, null, $input);
        if ($process->run() == 0) {
            return json_decode($process->getOutput(), true);
        }
        throw new RoutingException(sprintf('Routing error: %s', $process->getErrorOutput()));
    }

    public function getRoutingFilter()
    {
        if (is_null($this->routingFilter)) {
            $this->routingFilter = $this->buildRoutingFilterFromRules($this->rules, $this->defaultRule);
        }

        return $this->routingFilter;
    }

    public function buildRoutingFilterFromRules($rules, DefaultRoutingRule $defaultRule)
    {
        $filter = "";

        if (count($this->getRules())) {
            $first = true;
            foreach ($rules as $rule) {
                $filter .= ($first ? "if " : " elif ").$rule->getFilterExpr()." then ".$rule->getQueueNameExpr();
                $first = false;
            }

            $filter .= " else ".$defaultRule->getQueueNameExpr()." end";
        } else {
            $filter = $defaultRule->getQueueNameExpr();
        }

        return $filter;
    }
}
