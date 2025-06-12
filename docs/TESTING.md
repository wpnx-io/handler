# Testing Guide

## Running Tests

### Basic Test Execution

```bash
# Run all tests
composer test

# Run specific test class
vendor/bin/phpunit tests/HandlerTest.php

# Run specific test method
vendor/bin/phpunit --filter testRunWithWordPressFile
```

### Code Coverage

```bash
# Generate HTML coverage report
composer test:coverage

# View coverage report
open coverage/index.html
```

### Test Organization

Tests are organized by namespace mirroring the source structure:

```
tests/
├── HandlerTest.php
├── ConfigurationTest.php
├── Environment/
│   └── EnvironmentTest.php
├── Processors/
│   ├── DirectoryProcessorTest.php
│   ├── MultisiteProcessorTest.php
│   ├── PhpFileProcessorTest.php
│   ├── SecurityProcessorTest.php
│   ├── StaticFileProcessorTest.php
│   ├── TrailingSlashProcessorTest.php
│   └── WordPressProcessorTest.php
└── Security/
    └── PathValidatorTest.php
```

## Writing Tests

### Test Structure

All test classes should:
- Extend `PHPUnit\Framework\TestCase`
- Use strict types: `declare(strict_types=1);`
- Follow PSR-12 coding standards
- Include comprehensive assertions

### Example Test

```php
<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Configuration;

class ConfigurationTest extends TestCase
{
    public function testRequiresWebRoot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Configuration "paths.web_root" is required');

        new Configuration();
    }
}
```

### Testing File Operations

Many tests require temporary file systems. Use the pattern:

```php
protected function setUp(): void
{
    parent::setUp();
    
    $this->testRoot = sys_get_temp_dir() . '/wpnx-test-' . uniqid();
    mkdir($this->testRoot, 0777, true);
    
    // Create test files
    file_put_contents($this->testRoot . '/index.php', '<?php // Test');
}

protected function tearDown(): void
{
    parent::tearDown();
    $this->removeDirectory($this->testRoot);
}

private function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? $this->removeDirectory($path) : unlink($path);
    }
    rmdir($dir);
}
```

### Testing Exit Behavior

Some methods call `exit()`. To test these:

```php
public function testMethodThatExits(): void
{
    ob_start();
    $exited = false;
    $result = false;
    
    try {
        $result = $handler->methodThatMightExit();
    } catch (\Exception $e) {
        if ($e->getMessage() === '' || strpos($e->getMessage(), 'exit') !== false) {
            $exited = true;
        }
    }
    
    $output = ob_get_clean();
    
    $this->assertTrue($result || $exited);
}
```

### Data Providers

Use data providers for testing multiple scenarios:

```php
/**
 * @dataProvider pathTraversalProvider
 */
public function testPathTraversalPrevention(string $maliciousPath): void
{
    $this->expectException(SecurityException::class);
    $this->validator->validate($maliciousPath);
}

public function pathTraversalProvider(): array
{
    return [
        'simple traversal' => ['../'],
        'double traversal' => ['../../'],
        'encoded traversal' => ['%2e%2e/'],
        'double encoded' => ['%252e%252e%252f'],
    ];
}
```

## Code Quality

### PHPStan

```bash
# Run static analysis
composer phpstan

# Check specific file
vendor/bin/phpstan analyse src/Handler.php
```

### Code Style

```bash
# Check code style
composer phpcs

# Fix code style issues
composer phpcs:fix
```

## Continuous Integration

The package includes GitHub Actions configuration for:
- Running tests on PHP 8.0, 8.1, 8.2, and 8.3
- Testing with Symfony MIME 6.x and 7.x
- Code coverage reporting with Codecov
- PHPStan static analysis
- PSR-12 code style checking

## Debugging Tests

### Verbose Output

```bash
# Run with verbose output
vendor/bin/phpunit -v

# Show test names
vendor/bin/phpunit --testdox
```

### Debug Specific Issues

1. **Path Issues on macOS**: Temp directories may have symlinks (e.g., `/var` → `/private/var`)
2. **Handler Return Types**: The Handler's `run()` method returns `?string` for file paths
3. **File Permissions**: Ensure test files have proper permissions
4. **Multisite Testing**: Remember to create `/wp/` directory structure for multisite tests

## Best Practices

1. **Isolation**: Each test should be independent
2. **Cleanup**: Always clean up temporary files
3. **Assertions**: Use specific assertions (`assertSame` vs `assertEquals`)
4. **Coverage**: Aim for high coverage but focus on meaningful tests
5. **Security**: Test security features thoroughly
6. **Edge Cases**: Test boundary conditions and error paths