<?php

/**
 * AWS Lambda Example
 *
 * This example shows configuration optimized for AWS Lambda environments.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WpNx\Handler\Handler;
use WpNx\Handler\Configuration;

// Lambda-optimized configuration
$config = new Configuration([
    'web_root' => __DIR__,

    // Force Lambda mode (usually auto-detected)
    'lambda' => true,

    // Multisite support
    'multisite' => true,

    // Additional Lambda directories if needed
    'lambda' => [
        'enabled' => true,
        'directories' => [
            '/tmp/uploads',
            '/tmp/cache',
            '/tmp/sessions',
            '/tmp/wordpress-temp',
        ],
    ],
]);

// Create and run the handler
$result = (new Handler($config))->run();

// If a file path is returned, require it in global scope
if ($result) {
    require $result;
}
