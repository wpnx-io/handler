<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Multisite processor
 *
 * Handles WordPress Multisite URL rewriting for subdirectory installations.
 * This processor rewrites the $_SERVER variables to allow subsequent processors
 * to work with the rewritten paths while preserving the original REQUEST_URI.
 */
class MultisiteProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        // Skip if multisite is not enabled
        if (!$config->get('multisite.enabled', false)) {
            return null;
        }

        // Get path from PHP_SELF which was set from REQUEST_URI
        $path = $request->server->get('PHP_SELF') ?: $request->getPathInfo();
        $pattern = $config->get('multisite.pattern');
        $replacement = $config->get('multisite.replacement');

        // Check if pattern and replacement are configured
        if (!$pattern || !$replacement) {
            return null;
        }

        // Try to rewrite the path using the multisite pattern
        $rewrittenPath = preg_replace($pattern, $replacement, $path);

        // Check if preg_replace failed or if no replacement was made
        if ($rewrittenPath === null || $rewrittenPath === $path) {
            return null;
        }

        // Update PHP_SELF to the rewritten path
        $request->server->set('PHP_SELF', $rewrittenPath);

        // Store original path for reference
        $request->attributes->set('original_path', $path);

        return null; // Continue to next processor
    }
}
