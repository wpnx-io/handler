<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Processors;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Processors\TrailingSlashProcessor;
use WpNx\Handler\Configuration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrailingSlashProcessorTest extends TestCase
{
    private string $testRoot;
    private TrailingSlashProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-trailing-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create test directories
        mkdir($this->testRoot . '/admin', 0777, true);
        mkdir($this->testRoot . '/wp-admin', 0777, true);
        mkdir($this->testRoot . '/nested/deep', 0777, true);

        // Create test files
        file_put_contents($this->testRoot . '/test.php', '<?php echo "test";');
        file_put_contents($this->testRoot . '/admin/index.php', '<?php echo "admin";');

        $this->processor = new TrailingSlashProcessor();
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

    public function testProcessDirectoryWithoutTrailingSlash(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/admin');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertEquals(307, $result->getStatusCode());
        $this->assertEquals('/admin/', $result->getTargetUrl());
    }

    public function testProcessDirectoryWithTrailingSlash(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/admin/');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Should return null when path already has trailing slash');
    }

    public function testProcessFileWithoutTrailingSlash(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/test.php');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Should return null for files');
    }

    public function testProcessNonExistentPath(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/nonexistent');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Should return null for non-existent paths');
    }

    public function testProcessEmptyPath(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        // Create a request and manually set empty path
        $request = Request::create('/');
        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('pathInfo');
        $property->setAccessible(true);
        $property->setValue($request, '');

        $result = $this->processor->process($request, $config);

        // Empty path should redirect to /
        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertEquals(307, $result->getStatusCode());
        $this->assertEquals('/', $result->getTargetUrl());
    }

    public function testProcessWithQueryString(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/admin?page=users&action=edit');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertEquals(307, $result->getStatusCode());
        // Query parameters might be reordered by Symfony
        $this->assertStringStartsWith('/admin/?', $result->getTargetUrl());
        $this->assertStringContainsString('page=users', $result->getTargetUrl());
        $this->assertStringContainsString('action=edit', $result->getTargetUrl());
    }

    public function testProcessNestedDirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/nested/deep');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertEquals('/nested/deep/', $result->getTargetUrl());
    }

    public function testProcessWordPressAdminDirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/wp-admin');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertEquals('/wp-admin/', $result->getTargetUrl());
    }

    public function testProcessPreservesMethod(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        // Test with POST request
        $request = Request::create('/admin', 'POST', ['data' => 'value']);

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        // 307 preserves the request method
        $this->assertEquals(307, $result->getStatusCode());
        $this->assertEquals('/admin/', $result->getTargetUrl());
    }
}
