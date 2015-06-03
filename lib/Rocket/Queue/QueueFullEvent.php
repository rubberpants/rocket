<?php

namespace Rocket\Queue;

class QueueFullEvent extends QueueEvent
{
    protected $jobData;

    public function __construct(QueueInterface $queue, $jobData)
    {
        parent::__construct($queue);
        $this->jobData = $jobData;
    }

    public function getJobData()
    {
        return $this->jobData;
    }
}
