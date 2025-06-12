<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WpNx\Handler\Configuration;

/**
 * Interface for request processors
 *
 * Each processor handles a specific aspect of request processing
 * and can either return a modified Request, a Response, or null.
 */
interface ProcessorInterface
{
    /**
     * Process the request
     *
     * @param Request $request The HTTP request
     * @param Configuration $config The application configuration
     *
     * @return Request|Response|null
     *         - Request: Modified request to pass to the next processor
     *         - Response: Immediately return this response
     *         - null: Continue to the next processor with the current request
     */
    public function process(Request $request, Configuration $config): Request|Response|null;
}
