<?php

namespace Rocket;

trait ObjectCacheTrait
{
    protected $objects = [];

    /**
     * Get an instance of an object if available. If not, use $createClosure
     * to create it. If $maxCache objects stored, pick a random
     * object to delete before adding more.
     *
     * @param string  $type
     * @param string  $key
     * @param Closure $createClosure($key) -> instance
     * @param int     $maxCache
     *
     * @return mixed
     */
    public function getCachedObject($type, $key, \Closure $createClosure, $maxCache = 16)
    {
        if (!array_key_exists($type, $this->objects)) {
            $this->objects[$type] = [];
        }

        if (!array_key_exists($key, $this->objects[$type])) {
            while (count($this->objects[$type]) >= $maxCache) {
                $index = array_rand($this->objects[$type]);
                unset($this->objects[$type][$index]);
            }

            $this->objects[$type][$key] = $createClosure($key);
        }

        return $this->objects[$type][$key];
    }

    /**
     * Get all the cached objects of the specified type.
     *
     * @param string $type
     *
     * @return array($key => mixed)
     */
    public function getCachedObjects($type)
    {
        if (!array_key_exists($type, $this->objects)) {
            $this->objects[$type] = [];
        }

        return $this->objects[$type];
    }

    /**
     * Clear the cached objects of the specified type.
     *
     * @param string $type
     */
    public function clearCachedObjects($type)
    {
        $this->objects[$type] = [];
    }
}
