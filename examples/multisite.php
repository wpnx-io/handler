<?php

/**
 * WordPress Multisite Handler Example
 *
 * This example shows configuration for WordPress Multisite
 * with subdirectory installation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WpNx\Handler\Handler;
use WpNx\Handler\Configuration;

// Simple multisite configuration
$config = new Configuration([
    'web_root' => __DIR__ . '/public',
    'multisite' => true  // Use default multisite settings
]);

// Create and run the handler
$result = (new Handler($config))->run();

// If a file path is returned, require it in global scope
if ($result) {
    require $result;
}
