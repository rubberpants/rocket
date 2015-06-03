<?php

namespace Rocket\Plugin;

use Rocket\RocketInterface;

abstract class AbstractPlugin implements PluginInterface
{
    use \Rocket\LogTrait;
    use \Rocket\Plugin\EventTrait;
    use \Rocket\Config\ConfigTrait;
    use \Rocket\Redis\RedisTrait;

    protected $rocket;

    public function __construct(RocketInterface $rocket)
    {
        $this->rocket = $rocket;
    }

    public function getRocket()
    {
        return $this->rocket;
    }

    abstract public function register();
}
