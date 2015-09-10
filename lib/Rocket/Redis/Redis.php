<?php

namespace Rocket\Redis;

use Predis\Connection\ConnectionException;
use Predis\Connection\Aggregate\MasterSlaveReplication;
use Rocket\Config\Config;
use Symfony\Component\EventDispatcher\GenericEvent;

class Redis implements RedisInterface
{
    use \Rocket\LogTrait;
    use \Rocket\Config\ConfigTrait;
    use \Rocket\Plugin\EventTrait;

    protected $client;
    protected $recording;
    protected $recordedCommands;

    public function getClient()
    {
        if (is_null($this->client)) {
            $this->client = $this->createClient();
        }

        $this->client->reconnectIfNeeded();

        return $this->client;
    }

    public function openPipeline()
    {
        if ($this->recording) {
            throw new ClientException('Nested pipelines not supported');
        }

        $this->recording = true;
        $this->recordedCommands = [];
    }

    public function request(\Closure $function)
    {
        if ($this->recording) {
            $this->recordedCommands[] = $function;

            return;
        }

        return $function($this->getClient());
    }

    public function closePipeline($timeout = 0)
    {
        if ($this->recording) {
            $this->executeCommands($this->recordedCommands, $timeout);
            $this->recordedCommands = [];
            $this->recording = false;
        }
    }

    public function cancelPipeline()
    {
        $this->recordedCommands = [];
        $this->recording = false;
    }

    public function disconnect()
    {
        $this->getClient()->disconnect();
    }

    public function getStringType($key)
    {
        return new StringType($this, $key);
    }

    public function getListType($key)
    {
        return new ListType($this, $key);
    }

    public function getSetType($key)
    {
        return new SetType($this, $key);
    }

    public function getHashType($key)
    {
        return new HashType($this, $key);
    }

    public function getSortedSetType($key)
    {
        return new SortedSetType($this, $key);
    }

    public function getUniqueListType($key)
    {
        return new UniqueListType($this, $key);
    }

    public function promoteToMaster()
    {
        return $this->getClient()->slaveof('NO', 'ONE');
    }

    public function shutdown()
    {
        return $this->getClient()->shutdown();
    }

    public function getStatus()
    {
        return $this->getClient()->info('all');
    }

    public function isRunning()
    {
        return $this->getClient()->ping() == 'PONG';
    }

    protected function createClient()
    {
        try {
            $options = [
                'prefix' => $this->getConfig()->getApplicationName().':',
            ];

            $connections = $this->getConfig()->getRedisConnections();

            if (count($connections) > 1) {
                $options['replication'] = new MasterSlaveReplication();
            }

            $client = new Client($connections, $options);

            $client->setLogger($this->getLogger());
            $client->setLogContext('conn', $connections);
            $client->setEventDispatcher($this->getEventDispatcher());

            $this->getEventDispatcher()->dispatch(Client::EVENT_CONNECT, new ClientEvent($client));

            return $client;
        } catch (ConnectionException $e) {
            $this->error($e->getMessage());

            return false;
        }

        return false;
    }

    protected function connectionError($message)
    {
        $this->error($message);
        $this->getEventDispatcher()->dispatch(Client::EVENT_ERROR, new GenericEvent($message));
        throw new ClientException($message);
    }

    protected function executeCommands($functions, $timeout = 0)
    {
        $retries = 0;
        $start = time();

        while (time() < ($start+$timeout) || $timeout == 0) {
            try {
                $this->getClient()->pipeline(function ($pipe) use ($functions) {
                    //$pipe->multi(); //Makes pipelines atomic, but cannot be used in master/slave configurations
                    foreach ((array) $functions as $function) {
                        $function($pipe);
                    }
                    //$pipe->exec();
                });

                return;
            } catch (\Exception $e) {
                $this->warning($e->getMessage());
                if ($timeout == 0) {
                    break;
                }
                sleep(ceil(++$retries*2));
            }
        }

        $this->error('Timed out trying to execute pipeline');
    }
}
