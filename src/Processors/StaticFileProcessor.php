<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;

/**
 * Static file processor
 *
 * Serves static files (non-PHP) with appropriate MIME types
 * and caching headers.
 */
class StaticFileProcessor implements ProcessorInterface
{
    private MimeTypes $mimeTypes;

    public function __construct()
    {
        $this->mimeTypes = new MimeTypes();
    }

    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->server->get('PHP_SELF') ?: $request->getPathInfo();
        $webRoot = $config->get('web_root');
        $fullPath = $webRoot . $path;

        // Check if it's a file and not PHP (case-insensitive)
        if (is_file($fullPath) && !str_ends_with(strtolower($path), '.php')) {
            // Use BinaryFileResponse for efficient file serving
            $response = new BinaryFileResponse($fullPath);

            // Set MIME type
            $mimeType = $this->getMimeType($fullPath);
            $response->headers->set('Content-Type', $mimeType);

            return $response;
        }

        return null; // Continue to next processor
    }


    /**
     * Get MIME type for a file using Symfony MimeTypes
     */
    private function getMimeType(string $path): string
    {
        // Get extension from path
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Use Symfony's extension-based MIME type guessing
        $mimeTypes = $this->mimeTypes->getMimeTypes($extension);
        if (!empty($mimeTypes)) {
            return $mimeTypes[0];
        }

        // Fall back to content-based guessing if extension lookup fails
        $guessed = $this->mimeTypes->guessMimeType($path);
        if ($guessed && $guessed !== 'application/octet-stream') {
            return $guessed;
        }

        // Default
        return 'application/octet-stream';
    }
}
