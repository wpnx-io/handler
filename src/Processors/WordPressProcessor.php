<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WordPress processor
 *
 * Final fallback processor that routes all remaining requests
 * to WordPress index.php for handling.
 */
class WordPressProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        // Check if another processor has already set up a file to execute
        if ($request->server->has('SCRIPT_FILENAME')) {
            $scriptFilename = $request->server->get('SCRIPT_FILENAME');
            if (is_file($scriptFilename)) {
                return null; // Already handled by another processor
            }
        }

        // This is the final processor - route to WordPress index.php
        $webRoot = $config->get('web_root');
        $wpIndex = $config->get('wordpress_index', '/index.php');
        $indexPath = $webRoot . $wpIndex;

        if (!is_file($indexPath)) {
            throw new \RuntimeException(sprintf(
                'WordPress index.php not found at: %s',
                $indexPath
            ));
        }

        // Update server variables
        $request->server->set('PHP_SELF', $wpIndex);
        $request->server->set('SCRIPT_NAME', $wpIndex);
        $request->server->set('SCRIPT_FILENAME', $indexPath);

        return $request; // Return modified request
    }
}
