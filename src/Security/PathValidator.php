<?php

namespace WpNx\Handler\Security;

use WpNx\Handler\Exceptions\SecurityException;

/**
 * Path validation and security checks
 */
class PathValidator
{
    private string $webRoot;
    private bool $checkSymlinks;

    /**
     * Create a new path validator
     *
     * @param string $webRoot The web root directory
     * @param bool $checkSymlinks Whether to validate symlinks
     */
    public function __construct(string $webRoot, bool $checkSymlinks = true)
    {
        $realPath = realpath($webRoot);
        if ($realPath === false) {
            throw new \InvalidArgumentException("Invalid web root path: $webRoot");
        }
        $this->webRoot = $realPath;
        $this->checkSymlinks = $checkSymlinks;
    }

    /**
     * Validate a request path for security issues
     *
     * @param string $path The path to validate
     * @throws SecurityException If the path is invalid
     * @return string The validated path
     */
    public function validate(string $path): string
    {
        // Check for null bytes
        if (strpos($path, "\0") !== false) {
            throw new SecurityException("Null byte detected in path");
        }

        // Check for directory traversal patterns (including double-encoded)
        $patterns = [
            '../', '..\\',
            '%2e%2e/', '%2e%2e\\',
            '%252e%252e%252f', '%252e%252e%255c',
        ];
        foreach ($patterns as $pattern) {
            if (stripos($path, $pattern) !== false) {
                throw new SecurityException("Directory traversal attempt detected");
            }
        }

        // Check for dangerous characters
        if (preg_match('/[\x00-\x1f\x7f]/', $path)) {
            throw new SecurityException("Invalid characters in path");
        }

        return $path;
    }

    /**
     * Validate a file path is within the web root
     *
     * @param string $filePath The file path to validate
     * @throws SecurityException If the path is outside web root
     * @return string The validated file path
     */
    public function validateFilePath(string $filePath): string
    {
        // If not checking symlinks, just verify the path is within web root
        if (!$this->checkSymlinks) {
            // Basic check without resolving symlinks
            $normalizedPath = $this->normalizePath($filePath);
            $normalizedWebRoot = $this->normalizePath($this->webRoot);

            if (!str_starts_with($normalizedPath, $normalizedWebRoot)) {
                throw new SecurityException("Path outside web root");
            }
            return $filePath;
        }

        // Full symlink resolution check
        $realPath = realpath($filePath);

        if ($realPath === false) {
            // Path doesn't exist, check parent directory
            $dir = dirname($filePath);
            $realDir = realpath($dir);
            if ($realDir === false || !str_starts_with($realDir, $this->webRoot)) {
                throw new SecurityException("Path outside web root");
            }
            return $filePath;
        }

        if (!str_starts_with($realPath, $this->webRoot)) {
            throw new SecurityException("Path outside web root");
        }

        return $realPath;
    }

    /**
     * Normalize a path for comparison
     */
    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Check if a path component is a hidden file
     *
     * @param string $path The path to check
     * @return bool True if path contains hidden file/directory
     */
    public function isHiddenPath(string $path): bool
    {
        $parts = explode('/', trim($path, '/'));
        foreach ($parts as $part) {
            if ($part !== '' && $part[0] === '.' && $part !== '.' && $part !== '..') {
                return true;
            }
        }
        return false;
    }
}
