<?php

declare(strict_types=1);

// Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Set timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Ensure we have a clean environment
$_SERVER = array_merge($_SERVER, [
    'REQUEST_METHOD' => 'GET',
    'SERVER_NAME' => 'localhost',
    'SERVER_PORT' => '80',
    'HTTP_HOST' => 'localhost',
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/',
    'PHP_SELF' => '/index.php',
    'REMOTE_ADDR' => '127.0.0.1',
]);

// Clean up superglobals
$_GET = [];
$_POST = [];
$_REQUEST = [];
$_COOKIE = [];
$_FILES = [];
