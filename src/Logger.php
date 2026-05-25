<?php

/**
 * Logger interface for the Image Detection Client
 */

namespace Truthscan\ImageDetection;

/**
 * Logger interface
 */
interface Logger
{
    public function info(string $message): void;
    public function debug(string $message): void;
    public function warn(string $message): void;
    public function error(string $message): void;
}

/**
 * Default console logger implementation
 */
class DefaultConsoleLogger implements Logger
{
    private string $logLevel;
    private array $levels = [
        'debug' => 0,
        'info' => 1,
        'warn' => 2,
        'error' => 3,
    ];

    public function __construct(string $logLevel = 'info')
    {
        // Validate logLevel and fallback to 'info' if invalid
        if (!isset($this->levels[$logLevel])) {
            $this->logLevel = 'info';
        } else {
            $this->logLevel = $logLevel;
        }
    }

    private function timestamp(): string
    {
        return date('c'); // ISO 8601 format
    }

    private function shouldLog(string $level): bool
    {
        // Ensure both level and logLevel are valid before comparison
        if (!isset($this->levels[$level]) || !isset($this->levels[$this->logLevel])) {
            return false;
        }
        return $this->levels[$level] >= $this->levels[$this->logLevel];
    }

    public function info(string $message): void
    {
        if ($this->shouldLog('info')) {
            echo "[" . $this->timestamp() . "] INFO: {$message}\n";
        }
    }

    public function debug(string $message): void
    {
        if ($this->shouldLog('debug')) {
            echo "[" . $this->timestamp() . "] DEBUG: {$message}\n";
        }
    }

    public function warn(string $message): void
    {
        if ($this->shouldLog('warn')) {
            echo "[" . $this->timestamp() . "] WARN: {$message}\n";
        }
    }

    public function error(string $message): void
    {
        if ($this->shouldLog('error')) {
            error_log("[" . $this->timestamp() . "] ERROR: {$message}");
        }
    }
}

