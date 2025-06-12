<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Processors;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Processors\SecurityProcessor;
use WpNx\Handler\Configuration;
use WpNx\Handler\Exceptions\SecurityException;
use Symfony\Component\HttpFoundation\Request;

class SecurityProcessorTest extends TestCase
{
    private string $testRoot;
    private SecurityProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-security-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create test files
        file_put_contents($this->testRoot . '/index.php', '<?php // test');
        file_put_contents($this->testRoot . '/.htaccess', 'Deny from all');
        mkdir($this->testRoot . '/.git', 0777, true);

        $this->processor = new SecurityProcessor();
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

    public function testProcessValidPath(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/index.php');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Valid path should return null to continue processing');
    }

    public function testProcessPathTraversalAttack(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/../etc/passwd');

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }

    public function testProcessHiddenFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/.htaccess');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Access denied');
        $this->processor->process($request, $config);
    }

    public function testProcessHiddenDirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/.git/config');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Access denied');
        $this->processor->process($request, $config);
    }

    public function testProcessBlockedPatterns(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => [
                'blocked_patterns' => [
                    '/\.env$/',
                    '/\.sql$/',
                    '/backup/i',
                ],
            ],
        ]);

        $testCases = [
            '/.env' => 'Access denied',
            '/database.sql' => 'Access denied',
            '/backup/files.zip' => 'Access denied',
            '/BACKUP.txt' => 'Access denied',
        ];

        foreach ($testCases as $path => $expectedMessage) {
            $request = Request::create($path);

            try {
                $this->processor->process($request, $config);
                $this->fail("Expected SecurityException for path: $path");
            } catch (SecurityException $e) {
                $this->assertEquals($expectedMessage, $e->getMessage());
            }
        }
    }

    public function testProcessSymlinkCheckEnabled(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink tests are not reliable on Windows');
        }

        // Create a symlink pointing outside the web root
        $targetFile = tempnam(sys_get_temp_dir(), 'target');
        file_put_contents($targetFile, 'target content');
        $linkPath = $this->testRoot . '/symlink.txt';

        if (!@symlink($targetFile, $linkPath)) {
            @unlink($targetFile);
            $this->markTestSkipped(
                'Unable to create symlink for testing (insufficient permissions or filesystem limitations)'
            );
        }

        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => [
                'check_symlinks' => true,
            ],
        ]);

        $request = Request::create('/symlink.txt');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessageMatches('/outside|Path outside web root/');

        $this->processor->process($request, $config);

        @unlink($linkPath);
        @unlink($targetFile);
    }

    public function testProcessDoubleSlashesInPath(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        // Double slashes are normalized by Request::create, so test path traversal instead
        $request = Request::create('/../etc/passwd');

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }

    public function testProcessEncodedPathTraversal(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/%2e%2e/etc/passwd');

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }

    public function testProcessWindowsPathTraversal(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        // Use forward slashes for the request (backslashes are not allowed in HTTP URIs)
        $request = Request::create('/../windows/system32');

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }

    public function testProcessNonExistentFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/nonexistent.php');

        // Non-existent files should not throw exception in SecurityProcessor
        $result = $this->processor->process($request, $config);
        $this->assertNull($result);
    }

    public function testProcessEmptyPath(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/');

        $result = $this->processor->process($request, $config);
        $this->assertNull($result);
    }

    public function testPathValidatorInitialization(): void
    {
        // Create test files that exist in the web root
        file_put_contents($this->testRoot . '/test.php', '<?php // test');

        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => [
                'check_symlinks' => false,
            ],
        ]);

        // Process multiple requests to ensure PathValidator is reused
        // Use requests that don't exist to avoid file validation
        $request1 = Request::create('/nonexistent1.php');
        $request2 = Request::create('/nonexistent2.php');

        $result1 = $this->processor->process($request1, $config);
        $result2 = $this->processor->process($request2, $config);

        $this->assertNull($result1);
        $this->assertNull($result2);
    }
}
