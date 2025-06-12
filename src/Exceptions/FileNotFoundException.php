<?php

namespace WpNx\Handler\Exceptions;

/**
 * Exception thrown when a requested file is not found
 */
class FileNotFoundException extends HandlerException
{
    /**
     * Create a new file not found exception
     *
     * @param string $path The path that was not found
     */
    public function __construct(string $path)
    {
        parent::__construct("File not found: {$path}", 404);
    }
}
