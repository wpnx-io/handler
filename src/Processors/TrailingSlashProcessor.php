<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Trailing slash processor
 *
 * Ensures directories have trailing slashes by redirecting
 * requests without them using a 307 temporary redirect.
 *
 * Uses 307 instead of 301 to preserve HTTP method and request body,
 * which is important for POST requests to directories.
 */
class TrailingSlashProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->getPathInfo();
        $webRoot = $config->get('web_root');

        // Skip if already has trailing slash
        if (str_ends_with($path, '/')) {
            return null;
        }

        // Handle empty path
        if ($path === '') {
            return new RedirectResponse('/', 307);
        }

        // Check if it's a directory
        $fullPath = $webRoot . $path;

        // Check if it's a directory
        if (is_dir($fullPath)) {
            // Build redirect URL with trailing slash (use original path for redirect)
            $url = $path . '/';
            if ($request->getQueryString()) {
                $url .= '?' . $request->getQueryString();
            }

            return new RedirectResponse($url, 307);
        }

        return null; // Continue to next processor
    }
}
