<?php

declare(strict_types=1);

namespace WpNx\Handler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WpNx\Handler\Environment\Environment;
use WpNx\Handler\Exceptions\SecurityException;
use WpNx\Handler\Exceptions\FileNotFoundException;
use WpNx\Handler\Processors\SecurityProcessor;
use WpNx\Handler\Processors\TrailingSlashProcessor;
use WpNx\Handler\Processors\StaticFileProcessor;
use WpNx\Handler\Processors\PhpFileProcessor;
use WpNx\Handler\Processors\DirectoryProcessor;
use WpNx\Handler\Processors\MultisiteProcessor;
use WpNx\Handler\Processors\WordPressProcessor;
use WpNx\Handler\Processors\ProcessorInterface;

/**
 * Main request handler
 *
 * Processes incoming HTTP requests through a chain of processors
 * and executes WordPress when appropriate.
 */
class Handler implements HandlerInterface
{
    private Configuration $config;
    private Environment $environment;
    private array $processors = [];

    public function __construct(?Configuration $config = null)
    {
        $this->config = $config ?? new Configuration();
        $this->environment = new Environment($this->config);
        $this->initializeProcessors();
    }

    /**
     * Simple run method - handles request and sends response
     * @param Request|null $request Optional request object, creates from globals if not provided
     * @return string|null Returns null for direct responses, or file path string for WordPress execution
     */
    public function run(?Request $request = null): ?string
    {
        // Setup environment (Lambda directories, etc.)
        $this->environment->setup();

        $request = $this->prepareRequest($request);

        try {
            // Process request through the processor chain
            $request = $this->processRequest($request);
            if (!$request) {
                return null;
            }

            // Check if any processor set up PHP execution
            $filePath = $this->preparePhpEnvironment($request);
            if ($filePath) {
                return $filePath;
            }

            // No processor handled the request
            $this->sendNotFoundResponse();
            return null;
        } catch (\Exception $e) {
            $this->handleException($e);
            return null;
        }
    }

    /**
     * Prepare the request object
     */
    private function prepareRequest(?Request $request): Request
    {
        $request = $request ?? Request::createFromGlobals();

        // Clean up handler.php traces from server variables
        $request->server->remove('SCRIPT_FILENAME');
        $request->server->remove('SCRIPT_NAME');

        // Ensure PHP_SELF is set from REQUEST_URI
        $requestUri = $request->server->get('REQUEST_URI', '');
        // Remove query string from REQUEST_URI to get PHP_SELF
        $phpSelf = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $request->server->set('PHP_SELF', $phpSelf);

        return $request;
    }

    /**
     * Process the request through the processor chain
     * @return Request|null The final request object, or null if response was sent
     */
    private function processRequest(Request $request): ?Request
    {
        foreach ($this->processors as $processor) {
            $result = $processor->process($request, $this->config);

            if ($result instanceof Response) {
                // Processor returned a response - send it immediately
                $result->send();
                return null;
            } elseif ($result instanceof Request) {
                // Processor returned a modified request - continue with it
                $request = $result;
            }
            // null result means continue to next processor
        }

        return $request;
    }

    /**
     * Send a 404 Not Found response
     */
    private function sendNotFoundResponse(): void
    {
        $response = new Response('Not Found', 404);
        $response->send();
    }

    /**
     * Handle exceptions and send appropriate error responses
     */
    private function handleException(\Exception $e): void
    {
        if ($e instanceof SecurityException) {
            $response = new Response($e->getMessage(), 403);
            $response->send();
        } elseif ($e instanceof FileNotFoundException) {
            $response = new Response($e->getMessage(), 404);
            $response->send();
        } else {
            error_log(sprintf(
                'Handler error: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            $response = new Response('Internal Server Error', 500);
            $response->send();
        }
    }


    /**
     * Initialize the default processor chain
     */
    private function initializeProcessors(): void
    {
        $this->processors = [
            new SecurityProcessor(),
            new MultisiteProcessor(),
            new TrailingSlashProcessor(),
            new DirectoryProcessor(),
            new StaticFileProcessor(),
            new PhpFileProcessor(),
            new WordPressProcessor(),
        ];
    }

    /**
     * Prepare PHP environment for execution
     */
    private function preparePhpEnvironment(Request $request): ?string
    {
        // Get the file path from SCRIPT_FILENAME which processors set
        $filePath = $request->server->get('SCRIPT_FILENAME');
        if (!$filePath || !is_file($filePath)) {
            return null;
        }
        // Prepare environment variables from request
        $_SERVER['PATH_INFO'] = null;
        $_SERVER['PHP_SELF'] = $request->server->get('PHP_SELF');
        $_SERVER['SCRIPT_NAME'] = $request->server->get('SCRIPT_NAME');
        $_SERVER['SCRIPT_FILENAME'] = $request->server->get('SCRIPT_FILENAME');
        $_SERVER['REQUEST_URI'] = $request->server->get('REQUEST_URI');

        // Change to the correct working directory
        $workingDirectory = dirname($filePath);
        chdir($workingDirectory);

        return $filePath;
    }


    /**
     * Add a custom processor to the chain
     *
     * @param ProcessorInterface $processor The processor to add
     * @param int $priority Priority in the chain (lower = earlier)
     */
    public function addProcessor(ProcessorInterface $processor, int $priority = 100): void
    {
        // Insert processor at the specified priority position
        array_splice($this->processors, min($priority, count($this->processors)), 0, [$processor]);
    }
}
