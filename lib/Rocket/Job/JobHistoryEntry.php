<?php

namespace Rocket\Job;

class JobHistoryEntry
{
    protected $eventName;
    protected $details;
    protected $timestamp;

    public static function createFromString($string)
    {
        $args = json_decode($string, true);
        $entry = new JobHistoryEntry();
        $entry->setEventName($args['event_name']);
        $entry->setDetails($args['details']);
        $entry->setTimestamp(new \DateTime($args['timestamp']));

        return $entry;
    }

    public function getEventName()
    {
        return $this->eventName;
    }

    public function setEventName($eventName)
    {
        $this->eventName = $eventName;
    }

    public function getDetails()
    {
        return $this->details;
    }

    public function setDetails($details)
    {
        $this->details = $details;
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTime $timestamp)
    {
        $this->timestamp = $timestamp;
    }

    public function __toString()
    {
        return json_encode([
            'event_name' => $this->eventName,
            'details' => $this->details,
            'timestamp' => $this->timestamp->format(\DateTime::ISO8601),
        ]);
    }
}
