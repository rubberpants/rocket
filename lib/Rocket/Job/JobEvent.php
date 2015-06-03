<?php

namespace Rocket\Job;

use Symfony\Component\EventDispatcher\Event;

class JobEvent extends Event
{
    protected $job;

    public function __construct(JobInterface $job)
    {
        $this->job = $job;
    }

    public function getJob()
    {
        return $this->job;
    }
}
