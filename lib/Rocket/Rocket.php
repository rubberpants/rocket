<?php

namespace Rocket;

use Rocket\Redis\Redis;
use Rocket\Redis\SetType;
use Rocket\Config\ConfigInterface;
use Rocket\Plugin\PluginInterface;
use Rocket\Queue\Queue;
use Rocket\Queue\UUIDv4Generator;
use Rocket\Worker\Worker;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Monolog\Logger;
use Rocket\Queue\IdGeneratorInterface;

class Rocket implements RocketInterface
{
    use \Rocket\LogTrait;
    use \Rocket\ObjectCacheTrait;
    use \Rocket\Plugin\EventTrait;
    use \Rocket\Config\ConfigTrait;
    use \Rocket\Redis\RedisTrait;

    protected $queuesSet;
    protected $plugins = [];
    protected $jobsQueueHash;
    protected $idGenerator;

    /**
     * Creates a new instance of the main object.
     *
     * @param ConfigInterface      $config          The configuration object
     * @param Logger               $logger          The logger object
     * @param EventDispatcher      $eventDispatcher The event dispather object
     * @param IdGeneratorInterface $idGenerator     The object used to create new job ids
     *
     * @return Rocket
     */
    public function __construct(ConfigInterface $config, Logger $logger, EventDispatcher $eventDispatcher, IdGeneratorInterface $idGenerator = null)
    {
        $redis = new Redis();
        $redis->setConfig($config)
              ->setEventDispatcher($eventDispatcher)
              ->setLogger($logger);

        $this->setLogger($logger)
               ->setEventDispatcher($eventDispatcher)
               ->setConfig($config)
               ->setRedis($redis)
               ->registerPlugin('pump', new Plugin\Pump\PumpPlugin($this))
               ->registerPlugin('monitor', new Plugin\Monitor\MonitorPlugin($this))
               ->registerPlugin('aggregate', new Plugin\Aggregate\AggregatePlugin($this))
               ->registerPlugin('statistics', new Plugin\Statistics\StatisticsPlugin($this))
               ->registerPlugin('unique', new Plugin\UniqueJob\UniqueJobPlugin($this))
               ->registerPlugin('router', new Plugin\Router\RouterPlugin($this))
               ->registerPlugin('history', new Plugin\JobHistory\JobHistoryPlugin($this))
               ->registerPlugin('top', new Plugin\Top\TopPlugin($this))
               ->registerPlugin('groups', new Plugin\QueueGroups\QueueGroupsPlugin($this))
               ;

        $this->idGenerator = $idGenerator;

        if (is_null($this->idGenerator)) {
            $this->idGenerator = new UUIDv4Generator();
        }
    }

    /**
     * Registers a plugin with the system.
     *
     * @param string $name A string used to reference the plugin with getPlugin()
     *
     * @return Rocket
     */
    public function registerPlugin($name, PluginInterface $plugin)
    {
        $this->plugins[$name] = $plugin;
        $plugin->setConfig($this->getConfig());
        $plugin->setEventDispatcher($this->getEventDispatcher());
        $plugin->setRedis($this->getRedis());
        $plugin->setLogger($this->getLogger());
        $plugin->setLogContext('plugin', $name);
        $plugin->register();

        return $this;
    }

    /**
     * Distributes queued jobs to workers. Queues scheduled jobs. Triggers monitor events.
     * This is typically called in a loop by a dedicated process. The interval determines the resolution of the
     * scheduled jobs and monitor events, by blocking on the ready queue list for that amount of time.
     * Returns a list of jobs that were pumped, if any.
     *
     * @param int $interval
     *
     * @return array(string)
     */
    public function performOverheadTasks($interval = 1)
    {
        $this->getPlugin('monitor')->execute($this->getConfig()->getOverheadMaxEventsToHandle());
        $this->getPlugin('pump')->queueScheduledJobs($this->getConfig()->getOverheadMaxSchedJobsToQueue());

        return $this->getPlugin('pump')->pumpReadyQueue($this->getConfig()->getOverheadMaxJobsToPump(), $interval);
    }

    /**
     * Get the names of the queues.
     *
     * @return array(string)
     */
    public function getQueues()
    {
        return $this->getQueuesSet()->getItems();
    }

    /**
     * Get the number of queues.
     *
     * @return integer
     */
    public function getQueueCount()
    {
        return $this->getQueuesSet()->getCount();
    }

    /**
     * Get the object of the specified queue.
     *
     * @param string $queueName
     * @param int    $maxCache
     *
     * @return Queue
     */
    public function getQueue($queueName, $maxCache = 16)
    {
        return $this->getCachedObject('queue', $queueName, function ($queueName) {
            return new Queue($this, $queueName);
        });
    }

    /**
     * Get a job object by job id. You can save a lookup if you already know the queue name.
     * Caches specified number of objects.
     *
     * @param string $jobId
     * @param string $queueName
     * @param int    $maxCache
     *
     * @return Job
     */
    public function getJob($jobId, $queueName = null, $maxCache = 16)
    {
        if (is_null($queueName)) {
            $queueName = $this->getQueueNameByJobId($jobId);
        }

        return $this->getQueue($queueName, $maxCache)->getJob($jobId);
    }

    /**
     * Find out which queue a job belongs to.
     *
     * @param string $jobId
     *
     * @return string
     */
    public function getQueueNameByJobId($jobId)
    {
        $queueName = $this->getJobsQueueHash()->getField($jobId);

        if (is_null($queueName)) {
            throw new RocketException(sprintf('Job %s does not exist', $jobId));
        }

        return $queueName;
    }

    /**
     * Get a worker object by worker name.
     *  Caches specified number of objects.
     *
     * @param string $workerName
     *
     * @return Worker
     */
    public function getWorker($workerName, $maxCache = 16)
    {
        return $this->getCachedObject('worker', $workerName, function ($workerName) {
            $worker = new Worker($this, $workerName);
            $worker->setConfig($this->getConfig())
                   ->setRedis($this->getRedis())
                   ->setLogger($this->getLogger(), $this->getLogContext())
                   ->setEventDispatcher($this->getEventDispatcher());

            return $worker;
        }, $maxCache);
    }

    /**
     * Get a registered plugin object by name.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getPlugin($name)
    {
        return $this->plugins[$name];
    }

    /**
     * Get a redis type wrapper for the queues set.
     *
     * @return SetType
     */
    public function getQueuesSet()
    {
        if (is_null($this->queuesSet)) {
            $this->queuesSet = $this->getRedis()->getSetType('QUEUES');
        }

        return $this->queuesSet;
    }

    /**
     * Get a redis type wrapper for the jobs to queues map.
     *
     * @return HashType
     */
    public function getJobsQueueHash()
    {
        if (is_null($this->jobsQueueHash)) {
            $this->jobsQueueHash = $this->getRedis()->getHashType('JOBS_QUEUE');
        }

        return $this->jobsQueueHash;
    }

    public function getIdGenerator()
    {
        return $this->idGenerator;
    }
}
