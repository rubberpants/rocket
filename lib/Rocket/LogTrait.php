<?php

namespace Rocket;

use Monolog\Logger;

trait LogTrait
{
    protected $logger;
    protected $logContext = [];

    /**
     * Assign a logger to the instance. Optionally specify
     * a list of context values to include in all log messages
     * handled by the assigned logger.
     *
     * @param Logger $logger
     * @param array  $context
     *
     * @return LogTrait
     */
    public function setLogger(Logger $logger, $context = null)
    {
        $this->logger = $logger;
        if (is_array($context)) {
            $this->logContext = $context;
        }

        return $this;
    }

    /**
     * Get the logger instance.
     *
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Get the log context values.
     *
     * @return array($string => $string)
     */
    public function getLogContext()
    {
        return $this->logContext;
    }

    /**
     * Add a named value to the log context.
     *
     * @param string $name
     * @param string $value
     *
     * @return LogTrait
     */
    public function setLogContext($name, $value)
    {
        $this->logContext[$name] = $value;

        return $this;
    }

    /**
     * Log a message with the DEBUG level (most granular).
     *
     * @param string $message
     *
     * @return LogTrait
     */
    public function debug($message)
    {
        if ($this->logger) {
            $this->logger->log(Logger::DEBUG, $message, $this->logContext);
        }

        return $this;
    }

    /**
     * Log a message with the INFO level (less granular).
     *
     * @param string $message
     *
     * @return LogTrait
     */
    public function info($message)
    {
        if ($this->logger) {
            $this->logger->log(Logger::INFO, $message, $this->logContext);
        }

        return $this;
    }

    /**
     * Log a message with the WARNING level.
     *
     * @param string $message
     *
     * @return LogTrait
     */
    public function warning($message)
    {
        if ($this->logger) {
            $this->logger->log(Logger::WARNING, $message, $this->logContext);
        }

        return $this;
    }

    /**
     * Log a message with the ERROR level.
     *
     * @param string $message
     *
     * @return LogTrait
     */
    public function error($message)
    {
        if ($this->logger) {
            $this->logger->log(Logger::ERROR, $message, $this->logContext);
        }

        return $this;
    }

    /**
     * Log a message with the CRITICAL level.
     *
     * @param string $message
     *
     * @return LogTrait
     */
    public function critical($message)
    {
        if ($this->logger) {
            $this->logger->log(Logger::CRITICAL, $message, $this->logContext);
        }

        return $this;
    }
}
