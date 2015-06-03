<?php

namespace Rocket\Plugin\Top;

use Rocket\Plugin\AbstractPlugin;
use Rocket\RocketException;

class TopPlugin extends AbstractPlugin
{
    use \Rocket\ObjectCacheTrait;

    const METRIC_WAITING = 'waiting';
    const METRIC_RUNNING = 'running';

    protected $cacheTTL;

    public function register()
    {
        $this->cacheTTL = $this->getConfig()->getTopCacheTTL();
    }

    public function getTopQueues($metric = 'waiting', $limit = 10)
    {
        if ($results = $this->getCachedResults($cacheKey = sprintf('TOP:QUEUES:%s:%s', $metric, $limit))) {
            if (is_array($results) && count($results)) {
                return $results;
            }
        }

        $results = [];

        /* NOTE: All this business is expensive, which is why we cache it above */
        foreach ((array) $this->getRocket()->getQueues() as $queueName) {
            $queue = $this->getRocket()->getQueue($queueName);
            switch ($metric) {
                case self::METRIC_WAITING:
                    $results[$queueName] = $queue->getWaitingJobCount();
                    break;
                case self::METRIC_RUNNING:
                    $results[$queueName] = $queue->getRunningJobCount();
                    break;
                default:
                    throw new RocketException('Invalid top metric');
            }
        }

        asort($results);
        $results = array_reverse($results, true);
        $results = array_slice($results, 0, $limit, true);

        $this->storeCacheResults($cacheKey, $results);

        return $results;
    }

    public function getTopJobs($metric = 'running', $limit = 10)
    {
        $aggregate = $this->getRocket()->getPlugin('aggregate');

        if ($results = $this->getCachedResults($cacheKey = sprintf('TOP:JOBS:%s:%s', $metric, $limit))) {
            if (is_array($results) && count($results)) {
                return $results;
            }
        }

        $results = [];
        $now = new \DateTime();

        /* NOTE: All this business is expensive, which is why we cache it above */
        switch ($metric) {
            case self::METRIC_WAITING:
                foreach ((array) $aggregate->getAllWaitingJobs() as $jobId) {
                    $job = $this->getRocket()->getJob($jobId);
                    $results[$jobId] = intval($now->diff($job->getQueueTime())->format('%s'));
                }
                break;
            case self::METRIC_RUNNING:
                foreach ((array) $aggregate->getAllRunningJobs() as $jobId) {
                    $job = $this->getRocket()->getJob($jobId);
                    $results[$jobId] = intval($now->diff($job->getStartTime())->format('%s'));
                }
                break;
            default:
                throw new RocketException('Invalid top metric');
        }

        asort($results);
        $results = array_reverse($results, true);
        $results = array_slice($results, 0, $limit, true);

        $this->storeCacheResults($cacheKey, $results);

        return $results;
    }

    public function getCachedResults($key)
    {
        if (!is_null($results = $this->getTopCacheString($key)->get())) {
            return json_decode($results, true);
        }

        return false;
    }

    public function storeCacheResults($key, $results)
    {
        $this->getTopCacheString($key)->set(json_encode($results), $this->cacheTTL);
    }

    public function getTopCacheString($key)
    {
        return $this->getCachedObject('top_cache', $key, function ($key) {

            return $this->getRedis()->getStringType($key);
        });
    }
}
