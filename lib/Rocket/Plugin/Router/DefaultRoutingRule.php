<?php

namespace Rocket\Plugin\Router;

use Rocket\Config\ConfigException;

class DefaultRoutingRule extends RoutingRule
{
    public function getFilterExpr()
    {
        return;
    }

    public function setFilterExpr($expression)
    {
        throw new ConfigException('Default routing rule cannot have a filter expression');
    }
}
