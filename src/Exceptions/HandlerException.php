<?php

namespace WpNx\Handler\Exceptions;

/**
 * Base exception class for handler-related errors
 */
class HandlerException extends \Exception
{
    /**
     * Create a new handler exception
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
