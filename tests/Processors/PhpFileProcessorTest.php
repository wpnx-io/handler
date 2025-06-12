<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Processors;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Processors\PhpFileProcessor;
use WpNx\Handler\Configuration;
use Symfony\Component\HttpFoundation\Request;

class PhpFileProcessorTest extends TestCase
{
    private string $testRoot;
    private PhpFileProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-php-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create test PHP files
        file_put_contents($this->testRoot . '/index.php', '<?php echo "Index";');
        file_put_contents($this->testRoot . '/test.php', '<?php echo "Test";');
        file_put_contents($this->testRoot . '/admin.php', '<?php echo "Admin";');

        // Create PHP file with uppercase extension
        file_put_contents($this->testRoot . '/uppercase.PHP', '<?php echo "Uppercase";');

        // Create subdirectory with PHP files
        mkdir($this->testRoot . '/subdir', 0777, true);
        file_put_contents($this->testRoot . '/subdir/nested.php', '<?php echo "Nested";');

        // Create non-PHP files
        file_put_contents($this->testRoot . '/style.css', 'body { color: red; }');
        file_put_contents($this->testRoot . '/script.js', 'console.log("test");');
        file_put_contents($this->testRoot . '/readme.txt', 'README');

        // Create PHP-like files
        file_put_contents($this->testRoot . '/test.php.bak', 'backup');
        file_put_contents($this->testRoot . '/test.phps', 'source');

        $this->processor = new PhpFileProcessor();
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

    public function testProcessExistingPhpFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/index.php');
        $request->server->set('PHP_SELF', '/index.php');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals('/index.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessNestedPhpFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/subdir/nested.php');
        $request->server->set('PHP_SELF', '/subdir/nested.php');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals('/subdir/nested.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/subdir/nested.php', $result->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessNonExistentPhpFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/nonexistent.php');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Non-existent PHP file should return null');
    }

    public function testProcessNonPhpFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $nonPhpFiles = [
            '/style.css',
            '/script.js',
            '/readme.txt',
        ];

        foreach ($nonPhpFiles as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertNull($result, "Non-PHP file {$path} should return null");
        }
    }

    public function testProcessDirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/subdir/');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Directory should return null');
    }

    public function testProcessPhpLikeFiles(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $phpLikeFiles = [
            '/test.php.bak',
            '/test.phps',
        ];

        foreach ($phpLikeFiles as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertNull($result, "PHP-like file {$path} should return null");
        }
    }

    public function testProcessRootPhpFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/test.php');
        $request->server->set('PHP_SELF', '/test.php');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals('/test.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/test.php', $result->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessWithQueryString(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/test.php?foo=bar&baz=qux');
        $request->server->set('PHP_SELF', '/test.php');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals('/test.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/test.php', $result->server->get('SCRIPT_FILENAME'));
        // Query string should not affect the route
    }

    public function testProcessWithPathInfo(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        // PathInfo after PHP file
        $request = Request::create('/index.php/extra/path');

        $result = $this->processor->process($request, $config);

        // PhpFileProcessor only handles exact PHP file paths
        $this->assertNull($result, 'PHP file with path info should return null');
    }

    public function testProcessUppercasePhpExtension(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/uppercase.PHP');

        $result = $this->processor->process($request, $config);

        // Should be case-sensitive and not match .PHP
        $this->assertNull($result, 'Uppercase .PHP extension should return null');
    }

    public function testProcessMultiplePhpFiles(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $phpFiles = [
            '/index.php' => $this->testRoot . '/index.php',
            '/test.php' => $this->testRoot . '/test.php',
            '/admin.php' => $this->testRoot . '/admin.php',
            '/subdir/nested.php' => $this->testRoot . '/subdir/nested.php',
        ];

        foreach ($phpFiles as $path => $expectedFilePath) {
            $request = Request::create($path);
            $request->server->set('PHP_SELF', $path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(Request::class, $result);
            $this->assertEquals($path, $result->server->get('SCRIPT_NAME'));
            $this->assertEquals($expectedFilePath, $result->server->get('SCRIPT_FILENAME'));
        }
    }

    public function testRoutePropertiesConsistency(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/admin.php');
        $request->server->set('PHP_SELF', '/admin.php');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);

        // Verify all server variables are correctly set
        $this->assertEquals('/admin.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/admin.php', $result->server->get('SCRIPT_FILENAME'));
    }
}
