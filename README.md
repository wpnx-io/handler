# WpNx Handler

[![Tests](https://github.com/wpnx-io/handler/actions/workflows/tests.yml/badge.svg)](https://github.com/wpnx-io/handler/actions/workflows/tests.yml)
[![Code Coverage](https://codecov.io/gh/wpnx-io/handler/branch/master/graph/badge.svg)](https://codecov.io/gh/wpnx-io/handler)
[![Latest Stable Version](https://poser.pugx.org/wpnx/handler/v/stable)](https://packagist.org/packages/wpnx/handler)
[![License](https://poser.pugx.org/wpnx/handler/license)](https://packagist.org/packages/wpnx/handler)
[![PHP Version](https://img.shields.io/packagist/php-v/wpnx/handler)](https://packagist.org/packages/wpnx/handler)

A modern, secure PHP request handler for WordPress installations. Provides intelligent request routing, static file serving, and comprehensive security features. Optimized for traditional hosting, AWS Lambda, and containerized environments.

## Features

- **Simple API**: Just three lines of code to get started
- **Intelligent Routing**: Automatic handling of WordPress requests, static files, and directories
- **Security First**: Built-in protection against common attacks
- **Multisite Support**: Full support for WordPress Multisite installations
- **AWS Lambda Ready**: Automatic environment detection and optimization
- **Extensible**: Easy to add custom processing logic
- **Modern PHP**: PHP 8.0+ with strict typing and Symfony HttpFoundation

## Requirements

- PHP 8.0 or higher
- WordPress 5.0 or higher (recommended)

## Installation

Install via Composer:

```bash
composer require wpnx/handler
```

## Quick Start

### Minimal Setup

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use WpNx\Handler\Handler;

// Create handler and run
$result = (new Handler())->run();

// If a file path is returned, require it
if ($result) {
    require $result;
}
```

### With Configuration

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use WpNx\Handler\Handler;
use WpNx\Handler\Configuration;

$config = new Configuration([
    'web_root' => __DIR__,
    'multisite' => true,    // Enable multisite with defaults
    'lambda' => true,       // Force Lambda mode
]);

$result = (new Handler($config))->run();

if ($result) {
    require $result;
}
```

## Configuration

### Simple Configuration

```php
$config = new Configuration([
    'multisite' => true,    // Use default multisite settings
    'lambda' => false,      // Disable Lambda mode even if detected
]);
```

### Detailed Configuration

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `web_root` | string | Web root directory | Current directory |
| `wordpress_index` | string | WordPress index file | `'/index.php'` |
| `wp_directory` | string | WordPress directory | `'/wp'` |
| `index_files` | array | Index files to look for | `['index.php', 'index.html', 'index.htm']` |
| **Multisite** | | | |
| `multisite` | bool/array | Enable multisite | `false` |
| `multisite.enabled` | bool | Enable multisite (detailed mode) | `false` |
| `multisite.pattern` | string | URL match pattern | `'#^/[_0-9a-zA-Z-]+(/wp-.*)#'` |
| `multisite.replacement` | string | URL replacement | `'/wp$1'` |
| **Lambda** | | | |
| `lambda` | bool/array | Lambda mode | Auto-detect |
| `lambda.enabled` | bool | Force Lambda on/off | Auto-detect |
| `lambda.directories` | array | Directories to create | `['/tmp/uploads', '/tmp/cache', '/tmp/sessions']` |
| **Security** | | | |
| `security.allow_directory_listing` | bool | Allow directory listing | `false` |
| `security.check_symlinks` | bool | Validate symlinks | `true` |
| `security.blocked_patterns` | array | Blocked URL patterns | See below |

Default blocked patterns:
- `/\.git/`
- `/\.env/`
- `/\.htaccess/`
- `/composer\.(json|lock)/`
- `/wp-config\.php/`
- `/readme\.(txt|html|md)/i`

## WordPress Multisite

### Simple Mode

```php
$config = new Configuration([
    'multisite' => true  // Use default settings
]);
```

### Custom Pattern

```php
$config = new Configuration([
    'multisite' => [
        'enabled' => true,
        'pattern' => '#^/sites/([^/]+)(/wp-.*)#',
        'replacement' => '/wp$2'
    ]
]);
```

## AWS Lambda Support

### Auto-Detection

The handler automatically detects Lambda environments and:
- Creates necessary temp directories
- Configures PHP settings for Lambda
- Optimizes for Lambda constraints

### Manual Control

```php
// Force Lambda mode on (useful for testing)
$config = new Configuration(['lambda' => true]);

// Force Lambda mode off
$config = new Configuration(['lambda' => false]);

// Custom Lambda directories
$config = new Configuration([
    'lambda' => [
        'directories' => [
            '/tmp/uploads',
            '/tmp/my-app-cache',
        ]
    ]
]);
```

## Extending the Handler

### Custom Processors

Add custom request processing logic:

```php
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        if ($this->isMaintenanceMode()) {
            return new Response('Site under maintenance', 503);
        }
        return null; // Continue to next processor
    }
}

// Add to handler
$handler = new Handler($config);
$handler->addProcessor(new MaintenanceModeProcessor(), priority: 1);
$result = $handler->run();

if ($result) {
    require $result;
}
```

### Built-in Processors

The handler uses a chain of processors in this order:

1. **SecurityProcessor** - Validates requests and blocks attacks
2. **TrailingSlashProcessor** - Ensures directories have trailing slashes
3. **StaticFileProcessor** - Serves static files with proper MIME types
4. **PhpFileProcessor** - Handles direct PHP file requests
5. **DirectoryProcessor** - Looks for index files in directories
6. **MultisiteProcessor** - Handles multisite URL rewriting
7. **WordPressProcessor** - Falls back to WordPress index.php

## Examples

### Basic WordPress Site

```php
$result = (new Handler())->run();

if ($result) {
    require $result;
}
```

### Multisite Network

```php
$config = new Configuration([
    'multisite' => true,
    'web_root' => '/var/www/html'
]);

$result = (new Handler($config))->run();

if ($result) {
    require $result;
}
```

### High-Security Setup

```php
$config = new Configuration([
    'security' => [
        'blocked_patterns' => [
            '/\.git/',
            '/\.env/',
            '/vendor/',
            '/node_modules/',
            '/\.DS_Store$/',
            '/Thumbs\.db$/i',
        ]
    ]
]);

$result = (new Handler($config))->run();

if ($result) {
    require $result;
}
```

### Lambda Deployment

```php
$config = new Configuration([
    'lambda' => true,
    'multisite' => true
]);

$result = (new Handler($config))->run();

if ($result) {
    require $result;
}
```

## Testing

```bash
# Run tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/HandlerTest.php

# Check code style
vendor/bin/phpcs

# Fix code style
vendor/bin/phpcbf
```

## Security

The handler includes comprehensive security measures:

- **Path Traversal Protection**: Prevents `../` attacks
- **Symlink Validation**: Ensures symlinks stay within web root
- **Blocked Patterns**: Protects sensitive files
- **Null Byte Protection**: Prevents null byte injection
- **URL Encoding Protection**: Detects encoded attacks

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License.
