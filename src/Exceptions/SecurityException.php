<?php

namespace WpNx\Handler\Exceptions;

/**
 * Exception thrown when a security violation is detected
 */
class SecurityException extends HandlerException
{
    /**
     * Create a new security exception
     *
     * @param string $message The security violation message
     */
    public function __construct(string $message)
    {
        parent::__construct($message, 403);
    }
}
