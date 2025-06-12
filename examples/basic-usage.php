<?php

/**
 * Advanced WordPress Handler Example
 *
 * This example shows advanced configuration with error handling,
 * custom settings, and multisite support.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WpNx\Handler\Handler;
use WpNx\Handler\Configuration;

// Advanced configuration with all options
$config = new Configuration([
    // Web root directory
    'web_root' => '/var/www/html',

    // Multisite configuration (detailed mode)
    'multisite' => [
        'enabled' => true,
        'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
        'replacement' => '/wp$1',
    ],

    // Lambda configuration (will auto-detect by default)
    'lambda' => true,  // Force Lambda mode on

    // Custom security settings
    'security' => [
        'blocked_patterns' => [
            '/vendor/',
            '/node_modules/',
            '/\\.DS_Store$/',
        ],
    ],

    // Custom paths
    'wordpress_index' => '/index.php',
    'wp_directory' => '/wp',
    'index_files' => ['index.php', 'index.html', 'default.htm'],
]);

// Create and run the handler
// The run() method handles everything: request processing, routing, and response
$result = (new Handler($config))->run();

// If a file path is returned, require it in global scope
if ($result) {
    require $result;
}

// Note: Error handling is built into the Handler class.
// It will return appropriate HTTP status codes:
// - 403 for security violations
// - 404 for not found
// - 500 for server errors
