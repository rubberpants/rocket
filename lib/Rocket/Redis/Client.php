<?php

namespace Rocket\Redis;

use Predis\Command\CommandInterface;
use Predis\Connection\ConnectionException;

class Client extends \Predis\Client
{
    use \Rocket\LogTrait;
    use \Rocket\Plugin\EventTrait;

    const EVENT_CONNECT   = 'client.connect';
    const EVENT_RECONNECT = 'client.reconnect';
    const EVENT_ERROR     = 'client.error';
    const EVENT_TIMEOUT   = 'client.timeout';

    public function reconnectIfNeeded()
    {
        if (!$this->isConnected()) {
            try {
                $this->connect();
                $this->debug('Client reconnected');
                $this->getEventDispatcher()->dispatch(self::EVENT_RECONNECT, new ClientEvent($this));
            } catch (ConnectionException $e) {
                $this->error($e->getMessage());
                throw new ClientException('Could not reconnect');
            }
        }
    }

    public function executeCommand(CommandInterface $command)
    {
        $this->debug(strval($command));

        return parent::executeCommand($command);
    }
}
