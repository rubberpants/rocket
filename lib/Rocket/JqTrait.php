<?php

namespace Rocket;

use Symfony\Component\Process\Process;

trait JqTrait
{
    public function executeJq($filter, $input)
    {
        $commandLine = sprintf('jq -c %s', escapeshellarg($filter));

        if (is_null(json_decode($input))) {
            return;
        }

        $process = new Process($commandLine, null, null, $input);
        if ($process->run() == 0) {
            return json_decode($process->getOutput(), true);
        }
        throw new RocketException(sprintf('jq error: %s (%s)', $process->getErrorOutput(), $input));
    }
}
