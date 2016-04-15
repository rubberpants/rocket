<?php

namespace Rocket\Queue;

class UUIDv4Generator implements IdGeneratorInterface
{
    /**
     * Generate a UUID version 4 string. Used as the default behavior
     * for assigning new job IDs.
     *
     * @return string
     */
    public function generateId()
    {
        return sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
            mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 4095),
            bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
            mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
        );
    }
}
