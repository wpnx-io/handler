<?php

declare(strict_types=1);

namespace WpNx\Handler\Processors;

use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use WpNx\Handler\Exceptions\SecurityException;
use WpNx\Handler\Security\PathValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security processor
 *
 * Performs security checks on incoming requests to prevent
 * common attacks and unauthorized access.
 */
class SecurityProcessor implements ProcessorInterface
{
    private ?PathValidator $pathValidator = null;

    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->getPathInfo();
        $webRoot = $config->get('web_root');

        // Initialize PathValidator lazily
        if ($this->pathValidator === null) {
            $checkSymlinks = $config->get('security.check_symlinks', true);
            $this->pathValidator = new PathValidator($webRoot, $checkSymlinks);
        }

        // Use PathValidator for comprehensive security checks
        $this->pathValidator->validate($path);

        // Additional check for blocked patterns from configuration
        $blockedPatterns = $config->get('security.blocked_patterns', []);
        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                throw new SecurityException('Access denied');
            }
        }

        // Validate file path if it exists
        $fullPath = $webRoot . $path;
        if (file_exists($fullPath)) {
            $this->pathValidator->validateFilePath($fullPath);
        }

        return null; // Continue to next processor
    }
}
