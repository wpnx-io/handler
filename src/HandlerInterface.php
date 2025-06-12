<?php

declare(strict_types=1);

namespace WpNx\Handler;

/**
 * Interface for request handlers
 *
 * Defines the contract for classes that handle HTTP requests
 */
interface HandlerInterface
{
    /**
     * Simple run method - handles request and sends response
     *
     * This method creates a request from globals, handles it,
     * and sends the response to the browser.
     *
     * @return string|null Returns null for direct responses, or file path string for WordPress execution
     */
    public function run(): ?string;
}
