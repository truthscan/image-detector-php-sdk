<?php

/**
 * Error classes for AI Image Detection API Client
 */

namespace UndetectableAI\ImageDetection;

/**
 * Base API client error
 */
class ApiClientError extends \Exception
{
    public string $name;

    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->name = static::class;
    }
}

/**
 * Presign operation error
 */
class PresignError extends ApiClientError
{
}

/**
 * Upload operation error
 */
class UploadError extends ApiClientError
{
}

/**
 * Detect operation error
 */
class DetectError extends ApiClientError
{
}

/**
 * Query operation error
 */
class QueryError extends ApiClientError
{
}

/**
 * Credit check operation error
 */
class CreditCheckError extends ApiClientError
{
}

