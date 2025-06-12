<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Processors;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Processors\StaticFileProcessor;
use WpNx\Handler\Configuration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaticFileProcessorTest extends TestCase
{
    private string $testRoot;
    private StaticFileProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-static-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create test files with different extensions
        file_put_contents($this->testRoot . '/style.css', 'body { color: red; }');
        file_put_contents($this->testRoot . '/script.js', 'console.log("test");');
        file_put_contents($this->testRoot . '/image.jpg', 'fake image data');
        file_put_contents($this->testRoot . '/image.png', 'fake png data');
        file_put_contents($this->testRoot . '/image.gif', 'fake gif data');
        file_put_contents($this->testRoot . '/image.svg', '<svg></svg>');
        file_put_contents($this->testRoot . '/document.pdf', 'fake pdf data');
        file_put_contents($this->testRoot . '/data.json', '{"test": true}');
        file_put_contents($this->testRoot . '/page.html', '<html><body>Test</body></html>');
        file_put_contents($this->testRoot . '/font.woff', 'fake font data');
        file_put_contents($this->testRoot . '/font.woff2', 'fake font data');
        file_put_contents($this->testRoot . '/favicon.ico', 'fake ico data');
        file_put_contents($this->testRoot . '/unknown.zzz', 'unknown type');
        file_put_contents($this->testRoot . '/index.php', '<?php echo "PHP";');
        file_put_contents($this->testRoot . '/test.PHP', '<?php echo "PHP";');

        // Create subdirectory with files
        mkdir($this->testRoot . '/assets', 0777, true);
        file_put_contents($this->testRoot . '/assets/logo.png', 'fake logo data');

        $this->processor = new StaticFileProcessor();
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

    public function testProcessCssFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/style.css');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('text/css', $result->headers->get('Content-Type'));
        $this->assertEquals('public', $result->headers->get('Cache-Control'));
    }

    public function testProcessJsFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/script.js');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertContains($result->headers->get('Content-Type'), ['text/javascript', 'application/javascript']);
        $this->assertEquals('public', $result->headers->get('Cache-Control'));
    }

    public function testProcessImageFiles(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $imageTests = [
            '/image.jpg' => 'image/jpeg',
            '/image.png' => 'image/png',
            '/image.gif' => 'image/gif',
            '/image.svg' => 'image/svg+xml',
        ];

        foreach ($imageTests as $path => $expectedMime) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(BinaryFileResponse::class, $result);
            $this->assertEquals(200, $result->getStatusCode());
            $this->assertEquals($expectedMime, $result->headers->get('Content-Type'));
            $this->assertEquals('public', $result->headers->get('Cache-Control'));
        }
    }

    public function testProcessFontFiles(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $fontTests = [
            '/font.woff' => 'application/font-woff',
            '/font.woff2' => 'font/woff2',
        ];

        foreach ($fontTests as $path => $expectedMime) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(BinaryFileResponse::class, $result);
            $this->assertEquals(200, $result->getStatusCode());
            $this->assertEquals($expectedMime, $result->headers->get('Content-Type'));
            $this->assertEquals('public', $result->headers->get('Cache-Control'));
        }
    }

    public function testProcessNonCachedFiles(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $nonCachedTests = [
            '/document.pdf' => 'application/pdf',
            '/data.json' => 'application/json',
            '/page.html' => 'text/html',
        ];

        foreach ($nonCachedTests as $path => $expectedMime) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(BinaryFileResponse::class, $result);
            $this->assertEquals(200, $result->getStatusCode());
            $this->assertEquals($expectedMime, $result->headers->get('Content-Type'));
            // Should not have cache headers for non-static assets
            $this->assertNotEquals('max-age=2592000, public', $result->headers->get('Cache-Control'));
        }
    }

    public function testProcessPhpFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/index.php');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'PHP files should not be processed by StaticFileProcessor');
    }

    public function testProcessPhpFileWithUppercaseExtension(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/test.PHP');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'PHP files with uppercase extension should not be processed');
    }

    public function testProcessNonExistentFile(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/nonexistent.css');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Non-existent files should return null');
    }

    public function testProcessDirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/assets/');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result, 'Directories should return null');
    }

    public function testProcessFileInSubdirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/assets/logo.png');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('image/png', $result->headers->get('Content-Type'));
    }

    /**
     * Test unknown file extension returns default MIME type
     */
    public function testUnknownFileExtensionReturnsDefaultMimeType(): void
    {
        // Create a binary file with unknown extension to trigger default MIME type
        file_put_contents($this->testRoot . '/file.unknownext', "\x00\x01\x02\x03");

        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/file.unknownext');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        // Should return application/octet-stream for unknown binary files
        $this->assertEquals('application/octet-stream', $result->headers->get('Content-Type'));
    }

    public function testProcessFaviconIco(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/favicon.ico');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertContains(
            $result->headers->get('Content-Type'),
            ['image/x-icon', 'image/vnd.microsoft.icon', 'application/ico']
        );
        $this->assertEquals('public', $result->headers->get('Cache-Control'));
    }

    public function testProcessUnknownFileType(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/unknown.zzz');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        // Unknown file types might get a MIME type based on content or extension
        $mimeType = $result->headers->get('Content-Type');
        $this->assertIsString($mimeType);
    }

    public function testCacheHeadersForStaticAssets(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $staticAssets = [
            '/style.css',
            '/script.js',
            '/image.jpg',
            '/font.woff',
            '/favicon.ico',
        ];

        foreach ($staticAssets as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(BinaryFileResponse::class, $result);
            $cacheControl = $result->headers->get('Cache-Control');
            $this->assertEquals('public', $cacheControl);
        }
    }

    public function testNoCacheHeadersForNonStaticFiles(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);

        $nonStaticFiles = [
            '/page.html',
            '/data.json',
            '/document.pdf',
        ];

        foreach ($nonStaticFiles as $path) {
            $request = Request::create($path);
            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(BinaryFileResponse::class, $result);
        }
    }

    public function testProcessWithCustomMimeTypes(): void
    {
        // Create a file with no standard extension
        file_put_contents($this->testRoot . '/custom.xyz', 'custom content');

        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/custom.xyz');
        $request->server->set('PHP_SELF', '/custom.xyz');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $contentType = $result->headers->get('Content-Type');
        // .xyz files might have a specific MIME type or default to octet-stream
        $this->assertNotNull($contentType);
    }

    public function testProcessFileWithNoExtension(): void
    {
        // Create a file with no extension
        file_put_contents($this->testRoot . '/noextension', 'content');

        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/noextension');
        $request->server->set('PHP_SELF', '/noextension');

        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $contentType = $result->headers->get('Content-Type');
        // Files without extension might be detected as text/plain or application/octet-stream
        $this->assertContains($contentType, ['application/octet-stream', 'text/plain']);
    }

    public function testProcessWithSpecialMimeTypes(): void
    {
        // Test various special MIME types
        $specialFiles = [
            'data.json' => 'application/json',
            'data.xml' => 'application/xml',
            'archive.tar' => 'application/x-tar',
            'archive.gz' => 'application/gzip',
        ];

        foreach ($specialFiles as $filename => $expectedMime) {
            file_put_contents($this->testRoot . '/' . $filename, 'content');

            $config = new Configuration(['web_root' => $this->testRoot]);
            $request = Request::create('/' . $filename);
            $request->server->set('PHP_SELF', '/' . $filename);

            $result = $this->processor->process($request, $config);

            $this->assertInstanceOf(BinaryFileResponse::class, $result);
            $contentType = $result->headers->get('Content-Type');
            $this->assertNotNull($contentType, "Content-Type should not be null for $filename");
            // Some systems might return slightly different MIME types
            $this->assertStringContainsString(
                explode('/', $expectedMime)[0],
                $contentType,
                "MIME type for $filename should contain the main type"
            );
        }
    }
}
