<?php

/**
 * Basic WordPress Handler Example
 *
 * This example shows the minimal configuration needed
 * for a standard WordPress installation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WpNx\Handler\Handler;

// Minimal setup - just run with defaults
// Web root defaults to current directory
$result = (new Handler())->run();

// If a file path is returned, require it in global scope
if ($result) {
    require $result;
}
