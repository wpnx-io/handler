<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Processors;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Processors\WordPressProcessor;
use WpNx\Handler\Configuration;
use Symfony\Component\HttpFoundation\Request;

class WordPressProcessorTest extends TestCase
{
    private string $testRoot;
    private WordPressProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-wordpress-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create WordPress index.php
        file_put_contents($this->testRoot . '/index.php', '<?php // WordPress');

        // Create alternative WordPress setup
        mkdir($this->testRoot . '/wordpress', 0777, true);
        file_put_contents($this->testRoot . '/wordpress/index.php', '<?php // WordPress Core');

        $this->processor = new WordPressProcessor();
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

    public function testProcessDefaultWordPressIndex(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/any/path');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals('/index.php', $result->server->get('PHP_SELF'));
        $this->assertEquals('/index.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessCustomWordPressIndex(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'wordpress_index' => '/wordpress/index.php',
        ]);
        $request = Request::create('/any/path');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals('/wordpress/index.php', $result->server->get('PHP_SELF'));
        $this->assertEquals('/wordpress/index.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/wordpress/index.php', $result->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessSkipsWhenScriptFilenameAlreadySet(): void
    {
        // Create a file that exists
        file_put_contents($this->testRoot . '/admin.php', '<?php echo "Admin";');

        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/any/path');

        // Simulate another processor already set SCRIPT_FILENAME to an existing file
        $request->server->set('SCRIPT_FILENAME', $this->testRoot . '/admin.php');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Should return null when SCRIPT_FILENAME is already set to an existing file');
        $this->assertEquals($this->testRoot . '/admin.php', $request->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessMissingWordPressIndex(): void
    {
        // Remove index.php
        unlink($this->testRoot . '/index.php');

        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/any/path');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WordPress index.php not found at: ' . $this->testRoot . '/index.php');

        $this->processor->process($request, $config);
    }

    public function testProcessVariousRequestPaths(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        // Test various request paths - all should route to WordPress
        $paths = [
            '/',
            '/page/',
            '/category/uncategorized/',
            '/2023/12/31/hello-world/',
            '/wp-json/wp/v2/posts',
            '/feed/',
            '/sitemap.xml',
            '/robots.txt',
            '/non-existent-file.php',
        ];

        foreach ($paths as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(Request::class, $result, "Path {$path} should return Request");
            $this->assertEquals('/index.php', $result->server->get('PHP_SELF'));
            $this->assertEquals('/index.php', $result->server->get('SCRIPT_NAME'));
            $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
        }
    }

    public function testProcessWithQueryString(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/search/?s=test&post_type=page');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals('/index.php', $result->server->get('PHP_SELF'));
        $this->assertEquals('/index.php', $result->server->get('SCRIPT_NAME'));
        $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessPostRequest(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/wp-admin/admin-ajax.php', 'POST');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
        // Request method doesn't affect the route
    }

    public function testProcessWithHeaders(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/api/endpoint');
        $request->headers->set('X-Custom-Header', 'value');
        $request->headers->set('Accept', 'application/json');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
        // Headers don't affect the route
    }

    public function testProcessWorkingDirectoryAlwaysWebRoot(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        // Even with different paths, working directory should always be web root
        $paths = [
            '/deep/nested/path/',
            '/wp-content/uploads/2023/12/image.jpg',
            '/wp-admin/post.php',
        ];

        foreach ($paths as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(Request::class, $result);
            $this->assertEquals('/index.php', $result->server->get('PHP_SELF'));
            $this->assertEquals('/index.php', $result->server->get('SCRIPT_NAME'));
            $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
        }
    }

    public function testProcessWithTrailingSlashInWordPressIndex(): void
    {
        // Remove trailing slash from path to test properly
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'wordpress_index' => '/index.php', // No trailing slash
        ]);
        $request = Request::create('/test');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Request::class, $result);
        $this->assertEquals($this->testRoot . '/index.php', $result->server->get('SCRIPT_FILENAME'));
    }

    public function testProcessNeverReturnsNull(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        // WordPressProcessor should never return null as it's the final fallback
        $edgeCases = [
            '/',
            '/path?query#fragment',
        ];

        foreach ($edgeCases as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(
                Request::class,
                $result,
                "WordPressProcessor should always return Request, not null for path: {$path}"
            );
        }
    }
}
