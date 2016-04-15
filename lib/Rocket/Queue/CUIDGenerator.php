<?php

namespace Rocket\Queue;

class CUIDGenerator implements IdGeneratorInterface
{
    /*
        Compact Unique IDentifier

        12 digits of 0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ

        62^12 = 3.22^21 possible values

        IE.

        ZmHYX5GUCXPe
        B9F4Srf31VUJ
        A0FchimJddnZ
        mjcNERalPXD0
        s5heLXHm04bg
        CtTiUEsqVZXq
    */
    public function generateId()
    {
        $ucid = '';
        for ($i = 0; $i<12; $i++) {
            if (($r = mt_rand(0, 57)) < 10) {
                $ucid .= chr($r+48);
            } elseif ($r < 36) {
                $ucid .= chr($r+55);
            } else {
                $ucid .= chr($r+61);
            }
        }

        return $ucid;
    }
}
