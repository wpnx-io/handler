# API Documentation

## Core Classes

### Handler

The main request handler for WordPress installations.

```php
use WpNx\Handler\Handler;
use WpNx\Handler\Configuration;

// Simple usage
$handler = new Handler();
$result = $handler->run();

// If a file path is returned, require it in global scope
if ($result) {
    require $result;
}

// With configuration
$config = new Configuration([
    'web_root' => __DIR__,
    'multisite' => true,
]);

$handler = new Handler($config);
$result = $handler->run();
```

**Methods:**
- `__construct(?Configuration $config = null)` - Create a new handler instance
- `run(): ?string` - Handle the request. Returns null for direct responses, or file path for WordPress execution
- `handle(Request $request): Response` - Handle a specific request and return a response
- `addProcessor(ProcessorInterface $processor, int $priority = 100): void` - Add a custom processor

### Configuration

Manages all configuration options with validation and dot notation support.

```php
$config = new Configuration([
    'web_root' => __DIR__,
    'wordpress_index' => '/index.php',
    'wp_directory' => '/wp',
    'multisite' => [
        'enabled' => true,
        'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
        'replacement' => '/wp$1'
    ]
]);
```

**Methods:**
- `__construct(array $config = [])`
- `get(string $key, $default = null): mixed` - Get config value using dot notation
- `set(string $key, $value): void` - Set config value using dot notation
- `all(): array` - Get all configuration
- `isMultisiteEnabled(): bool`
- `getMultisitePattern(): ?string`
- `getMultisiteReplacement(): ?string`

### Processors

Processors handle different types of requests in a chain. Built-in processors include:

- **SecurityProcessor** - Validates request security
- **TrailingSlashProcessor** - Handles trailing slash redirects
- **StaticFileProcessor** - Serves static files
- **PhpFileProcessor** - Handles direct PHP file requests
- **DirectoryProcessor** - Handles directory index files
- **MultisiteProcessor** - Handles WordPress Multisite URL rewriting
- **WordPressProcessor** - Final fallback to WordPress index.php

You can add custom processors:

```php
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        if ($request->getPathInfo() === '/api/health') {
            return new Response('OK', 200);
        }
        
        return null; // Continue to next processor
    }
}

$handler = new Handler($config);
$handler->addProcessor(new CustomProcessor(), 50); // Priority 50
```

### PathValidator

Provides comprehensive security validation for file paths.

```php
use WpNx\Handler\Security\PathValidator;

$validator = new PathValidator('/var/www');

try {
    // Validate request path
    $safePath = $validator->validate($_SERVER['REQUEST_URI']);
    
    // Validate file path
    $safeFilePath = $validator->validateFilePath('/var/www/index.php');
    
    // Check for hidden files
    if ($validator->isHiddenPath('/path/to/.hidden')) {
        // Handle hidden file access
    }
} catch (SecurityException $e) {
    // Handle security violation
}
```

**Methods:**
- `__construct(string $webRoot)`
- `validate(string $path): string` - Validate request path
- `validateFilePath(string $filePath): string` - Validate file path is within web root
- `isHiddenPath(string $path): bool` - Check if path contains hidden components

### ExecutionContext

Encapsulates all information needed for PHP script execution.

```php
use WpNx\Handler\Context\ExecutionContext;

$context = new ExecutionContext([
    'filePath' => '/var/www/index.php',
    'workingDir' => '/var/www',
    'phpSelf' => '/index.php',
    'scriptName' => '/index.php',
    'scriptFilename' => '/var/www/index.php'
]);

// Access execution information
$filePath = $context->getFilePath();
$workingDir = $context->getWorkingDir();
```

**Methods:**
- `__construct(array $data)` - All fields are required
- `getFilePath(): string`
- `getWorkingDir(): string`
- `getPhpSelf(): string`
- `getScriptName(): string`
- `getScriptFilename(): string`
- `toArray(): array`

### Environment

Environment management with platform detection and Lambda support.

```php
use WpNx\Handler\Environment\Environment;
use WpNx\Handler\Configuration;

// Auto-detect platform
$config = new Configuration();
$env = new Environment($config);

// Force Lambda on/off
$config = new Configuration(['lambda' => ['enabled' => true]]);
$env = new Environment($config);

// Setup environment (creates directories for Lambda, etc.)
$env->setup();

// Check if running in Lambda
if ($env->isLambda()) {
    $info = $env->getInfo();
    // $info contains:
    // [
    //     'platform' => 'lambda',
    //     'lambda' => [
    //         'function_name' => 'my-function',
    //         'task_root' => '/var/task',
    //         'region' => 'us-east-1',
    //         'memory_limit' => 512,
    //         'function_version' => '$LATEST',
    //         'log_group' => '/aws/lambda/my-function',
    //         'log_stream' => '2023/01/01/[$LATEST]abc123'
    //     ]
    // ]
}

// Standard environment
$config = new Configuration(['lambda' => ['enabled' => false]]);
$env = new Environment($config);
$info = $env->getInfo();
// $info contains:
// [
//     'platform' => 'standard'
// ]
```

**Methods:**
- `__construct(Configuration $config)` - Create environment with configuration
- `setup(): void` - Setup environment based on detected platform
- `isLambda(): bool` - Check if running on Lambda platform
- `getInfo(): array` - Get platform and platform-specific information

**Configuration:**
- `lambda.enabled` - Force Lambda on (true) or off (false). If not set, auto-detects from environment
- `lambda.directories` - Custom directories to create in Lambda (defaults to `/tmp/uploads`, `/tmp/cache`, `/tmp/sessions`)

**Platform Detection:**
The Environment class automatically detects the platform based on environment variables:
- Lambda: Detected via `AWS_LAMBDA_FUNCTION_NAME`, `LAMBDA_TASK_ROOT`, or `_HANDLER`
- Standard: Default platform when no specific environment is detected
- Future platforms can be added (e.g., Kubernetes, Google Cloud Run)

## Interfaces

### HandlerInterface

```php
interface HandlerInterface
{
    /**
     * Handle a request and return a response
     */
    public function handle(Request $request): Response;

    /**
     * Simple run method - handles request and sends response
     * @return string|null Returns null for direct responses, or file path string for WordPress execution
     */
    public function run(): ?string;
}
```

### ProcessorInterface

```php
interface ProcessorInterface
{
    /**
     * Process a request
     * @return Request|Response|null Response to send, Request to execute, or null to continue
     */
    public function process(Request $request, Configuration $config): Request|Response|null;
}
```

### EnvironmentInterface

```php
interface EnvironmentInterface
{
    public function setup(): void;
    public function isLambda(): bool;
}
```

## Exceptions

- `SecurityException` - Thrown for security violations
- `HandlerException` - Base exception for handler errors
- `FileNotFoundException` - Thrown when required files are not found

## Configuration Reference

See the main README.md for complete configuration options.