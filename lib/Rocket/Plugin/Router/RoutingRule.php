<?php

namespace Rocket\Plugin\Router;

class RoutingRule
{
    protected $filterExpr;
    protected $queueNameExpr;

    public function getFilterExpr()
    {
        return $this->filterExpr;
    }

    public function getQueueNameExpr()
    {
        return $this->queueNameExpr;
    }

    public function setFilterExpr($expression)
    {
        $this->filterExpr = $expression;

        return $this;
    }

    public function setQueueNameExpr($expression)
    {
        $this->queueNameExpr = $expression;

        return $this;
    }
}
