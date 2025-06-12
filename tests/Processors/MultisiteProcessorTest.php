<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Processors;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Processors\MultisiteProcessor;
use WpNx\Handler\Configuration;
use Symfony\Component\HttpFoundation\Request;

class MultisiteProcessorTest extends TestCase
{
    private string $testRoot;
    private MultisiteProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-multisite-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create WordPress structure
        mkdir($this->testRoot . '/wp', 0777, true);
        mkdir($this->testRoot . '/wp/wp-admin', 0777, true);
        mkdir($this->testRoot . '/wp/wp-content', 0777, true);
        mkdir($this->testRoot . '/wp/wp-includes', 0777, true);

        // Create WordPress PHP files
        file_put_contents($this->testRoot . '/wp/wp-admin/index.php', '<?php // WP Admin');
        file_put_contents($this->testRoot . '/wp/wp-admin/admin.php', '<?php // Admin');
        file_put_contents($this->testRoot . '/wp/wp-admin/post.php', '<?php // Post');
        file_put_contents($this->testRoot . '/wp/wp-includes/ms-files.php', '<?php // MS Files');
        file_put_contents($this->testRoot . '/wp/wp-login.php', '<?php // Login');
        file_put_contents($this->testRoot . '/wp/wp-cron.php', '<?php // Cron');
        file_put_contents($this->testRoot . '/wp/index.php', '<?php // WordPress');
        file_put_contents($this->testRoot . '/index.php', '<?php // Root WordPress');

        $this->processor = new MultisiteProcessor();
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

    public function testProcessWithMultisiteDisabled(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => false,
            ],
        ]);

        $request = Request::create('/site1/wp-admin/');
        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Should return null when multisite is disabled');
        // Server variables should not be modified when multisite is disabled
        $this->assertNull($request->attributes->get('original_path'));
    }

    public function testProcessWithDefaultMultisitePattern(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
                'replacement' => '/wp$1',
            ],
        ]);

        // Test various multisite paths
        $testCases = [
            '/site1/wp-admin/' => '/wp/wp-admin/',
            '/site1/wp-admin/admin.php' => '/wp/wp-admin/admin.php',
            '/site1/wp-admin/post.php' => '/wp/wp-admin/post.php',
            '/site2/wp-login.php' => '/wp/wp-login.php',
            '/site2/wp-cron.php' => '/wp/wp-cron.php',
            '/my-site/wp-includes/ms-files.php' => '/wp/wp-includes/ms-files.php',
        ];

        foreach ($testCases as $requestPath => $expectedRewrittenPath) {
            $request = Request::create($requestPath);
            $result = $this->processor->process($request, $config);

            $this->assertNull($result, "Path {$requestPath} should return null");
            $this->assertEquals($expectedRewrittenPath, $request->server->get('PHP_SELF'));
            $this->assertEquals($requestPath, $request->attributes->get('original_path'));
        }
    }

    public function testProcessNonMultisitePaths(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
                'replacement' => '/wp$1',
            ],
        ]);

        // These paths should not match the multisite pattern
        $nonMultisitePaths = [
            '/',
            '/index.php',
            '/wp-admin/',
            '/wp-login.php',
            '/site1/',
            '/site1/index.php',
            '/site1/page/',
        ];

        foreach ($nonMultisitePaths as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertNull($result, "Path {$path} should return null");
            // original_path attribute should not be set for non-matching paths
            $this->assertNull($request->attributes->get('original_path'));
        }
    }

    public function testProcessWithMissingPattern(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'replacement' => '/wp$1',
            ],
        ]);

        $request = Request::create('/site1/wp-admin/');
        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Should return null when pattern is missing');
        $this->assertNull($request->attributes->get('original_path'));
    }

    public function testProcessWithMissingReplacement(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
            ],
        ]);

        $request = Request::create('/site1/wp-admin/');
        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Should return null when replacement is missing');
        $this->assertNull($request->attributes->get('original_path'));
    }

    /**
     * Test preg_replace returns null (invalid pattern)
     */
    public function testPregReplaceReturnsNull(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                // Invalid regex pattern that will cause preg_replace to return null
                'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*',  // Missing closing delimiter
                'replacement' => '/wp$1',
            ],
        ]);

        $request = Request::create('/site1/wp-admin/');

        // Suppress warning from invalid regex
        $previousLevel = error_reporting(0);
        $result = $this->processor->process($request, $config);
        error_reporting($previousLevel);

        // Should return null when preg_replace fails
        $this->assertNull($result);
        $this->assertNull($request->attributes->get('original_path'));
    }

    public function testProcessComplexSiteNames(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
                'replacement' => '/wp$1',
            ],
        ]);

        // Test various valid site names
        $siteNames = [
            'my-site',
            'site_1',
            'Test123',
            'site-with-long-name',
            '_underscore',
            '123numbers',
        ];

        foreach ($siteNames as $siteName) {
            $request = Request::create("/{$siteName}/wp-login.php");
            $result = $this->processor->process($request, $config);

            $this->assertNull($result, "Site name {$siteName} should return null");
            $this->assertEquals('/wp/wp-login.php', $request->server->get('PHP_SELF'));
        }
    }

    public function testProcessWithQueryString(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
                'replacement' => '/wp$1',
            ],
        ]);

        $request = Request::create('/site1/wp-admin/post.php?action=edit&post=123');
        $result = $this->processor->process($request, $config);

        $this->assertNull($result);
        $this->assertEquals('/wp/wp-admin/post.php', $request->server->get('PHP_SELF'));
        // Query strings are preserved in the request
        $this->assertEquals('action=edit&post=123', $request->getQueryString());
    }

    public function testProcessSimplifiedConfiguration(): void
    {
        // Test with simplified boolean configuration
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => true,
        ]);

        $request = Request::create('/site1/wp-admin/');
        $result = $this->processor->process($request, $config);

        // Should use default pattern and replacement
        $this->assertNull($result);
        $this->assertEquals('/wp/wp-admin/', $request->server->get('PHP_SELF'));
    }

    public function testProcessCustomPattern(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/blogs/([0-9]+)(/wp-.*)#',
                'replacement' => '$2',
            ],
        ]);

        $request = Request::create('/blogs/123/wp-admin/admin.php');
        $result = $this->processor->process($request, $config);

        $this->assertNull($result);
        $this->assertEquals('/wp-admin/admin.php', $request->server->get('PHP_SELF'));
        $this->assertEquals('/blogs/123/wp-admin/admin.php', $request->attributes->get('original_path'));
    }

    public function testRequestUriPreserved(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
                'replacement' => '/wp$1',
            ],
        ]);

        $request = Request::create('/site1/wp-admin/post.php?action=edit');

        // Store original REQUEST_URI
        $originalRequestUri = $request->server->get('REQUEST_URI');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result);
        // REQUEST_URI should not be changed
        $this->assertEquals($originalRequestUri, $request->server->get('REQUEST_URI'));
        // But PATH_INFO should be rewritten
        $this->assertEquals('/wp/wp-admin/post.php', $request->server->get('PHP_SELF'));
    }
}
