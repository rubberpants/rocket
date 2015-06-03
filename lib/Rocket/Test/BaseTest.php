<?php

namespace Rocket\Test;

use Symfony\Component\EventDispatcher\Event;

class BaseTest extends \PHPUnit_Framework_TestCase
{
    use \Rocket\Redis\RedisTrait;
    use \Rocket\Plugin\EventTrait;

    protected $eventsFired = [];

    public function setup()
    {
        $this->setRedis(Harness::getInstance()->getRedis());
        $this->setEventDispatcher(Harness::getInstance()->getEventDispatcher());
    }

    /**
     * Specify the name of an event we want to monitor.
     *
     * @param string $event
     */
    public function monitorEvent($event)
    {
        $this->getEventDispatcher()->addListener($event, [$this, 'eventTestListener']);
        $this->eventsFired[$event] = false;
    }

    /**
     * The handler that will be attached to monitor the event.
     *
     * @param Event $event
     */
    public function eventTestListener(Event $event)
    {
        $this->eventsFired[$event->getName()] = true;
    }

    /**
     * Assert that the monitored event actually fired at some point.
     * Optionally specify that the event should continue to be monitored.
     *
     * @param string  $event
     * @param boolean $remove
     */
    public function assertEventFired($event, $remove = true)
    {
        $this->assertTrue($this->eventsFired[$event]);
        unset($this->eventsFired[$event]);
        if ($remove) {
            $this->getEventDispatcher()->removeListener($event, [$this, 'eventTestListener']);
        }
    }

    /**
     * Assert that the monitored event never fired.
     * Optionally specify that the event should continue to be monitored.
     *
     * @param string  $event
     * @param boolean $remove
     */
    public function assertEventNotFired($event, $remove = true)
    {
        $this->assertFalse($this->eventsFired[$event]);
        unset($this->eventsFired[$event]);
        if ($remove) {
            $this->getEventDispatcher()->removeListener($event, [$this, 'eventTestListener']);
        }
    }

    /**
     * Assert that supplied function returns true for at least one element of the array.
     *
     * @param array   $array
     * @param Closure $function
     */
    public function assertArrayTrue($array, \Closure $function)
    {
        $found = false;
        foreach ($array as $key => $value) {
            if ($function($key, $value)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * Assert that the exception was thrown in the callback.
     *
     * @param Closure $callback
     */
    public function assertException(\Closure $callback, $expectedException = 'Exception', $expectedMessage = null)
    {
        if (!class_exists($expectedException) && !interface_exists($expectedException)) {
            $this->fail("An exception of type '$expectedException' does not exist.");
        }

        try {
            $callback();
        } catch (\Exception $e) {
            $class = get_class($e);
            $message = $e->getMessage();

            $extraInfo = $message ? " (message was $message)" : '';
            $this->assertInstanceOf($expectedException, $e, "Failed asserting the class of exception$extraInfo.");

            if ($expectedMessage !== null) {
                $this->assertContains($expectedMessage, $message, "Failed asserting the message of thrown $class.");
            }

            return;
        }

        $extraInfo = $expectedException !== 'Exception' ? " of type $expectedException" : '';
        $this->fail("Failed asserting that exception$extraInfo was thrown.");
    }
}
