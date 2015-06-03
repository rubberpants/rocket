<?php

namespace Rocket\Config;

trait ConfigTrait
{
    protected $config;

    public function setConfig(ConfigInterface $config)
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }
}
