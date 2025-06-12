<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHP file processor
 *
 * Handles direct requests to PHP files.
 */
class PhpFileProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        // Get path from PHP_SELF which may have been set by previous processors
        $path = $request->server->get('PHP_SELF');
        $webRoot = $config->get('web_root');
        $fullPath = $webRoot . $path;

        // Check if it's a PHP file that exists
        if (is_file($fullPath) && str_ends_with($path, '.php')) {
            // Update server variables
            $request->server->set('SCRIPT_NAME', $path);
            $request->server->set('SCRIPT_FILENAME', $fullPath);
            // PHP_SELF is already set correctly by DirectoryProcessor or from the original request

            return $request; // Return modified request
        }

        return null; // Continue to next processor
    }
}
