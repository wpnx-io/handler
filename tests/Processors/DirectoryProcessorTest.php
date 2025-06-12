<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Processors;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Processors\DirectoryProcessor;
use WpNx\Handler\Configuration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DirectoryProcessorTest extends TestCase
{
    private string $testRoot;
    private DirectoryProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-dir-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create test directories
        mkdir($this->testRoot . '/dir1', 0777, true);
        mkdir($this->testRoot . '/dir2', 0777, true);
        mkdir($this->testRoot . '/dir3', 0777, true);
        mkdir($this->testRoot . '/dir4', 0777, true);
        mkdir($this->testRoot . '/empty', 0777, true);

        // Create various index files
        file_put_contents($this->testRoot . '/dir1/index.php', '<?php echo "Dir1 Index";');
        file_put_contents($this->testRoot . '/dir2/index.html', '<h1>Dir2 Index</h1>');
        file_put_contents($this->testRoot . '/dir3/index.htm', '<h1>Dir3 Index</h1>');
        file_put_contents($this->testRoot . '/dir4/custom.php', '<?php echo "Not index";');

        // Create nested directories
        mkdir($this->testRoot . '/nested/deep', 0777, true);
        file_put_contents($this->testRoot . '/nested/deep/index.php', '<?php echo "Deep";');

        // Create test files (not directories)
        file_put_contents($this->testRoot . '/file.php', '<?php echo "File";');
        file_put_contents($this->testRoot . '/image.jpg', 'fake image data');

        $this->processor = new DirectoryProcessor();
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

    public function testProcessDirectoryWithPhpIndex(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/dir1/');

        $result = $this->processor->process($request, $config);

        // DirectoryProcessor should return null and update the request
        $this->assertNull($result);
        $this->assertEquals('/dir1/index.php', $request->server->get('PHP_SELF'));
        $this->assertTrue($request->attributes->get('directory_index'));
    }

    public function testProcessDirectoryWithHtmlIndex(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/dir2/');

        $result = $this->processor->process($request, $config);

        // DirectoryProcessor should return null and update the request
        $this->assertNull($result);
        $this->assertEquals('/dir2/index.html', $request->server->get('PHP_SELF'));
        $this->assertTrue($request->attributes->get('directory_index'));
    }

    public function testProcessDirectoryWithHtmIndex(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/dir3/');

        $result = $this->processor->process($request, $config);

        // DirectoryProcessor should return null and update the request
        $this->assertNull($result);
        $this->assertEquals('/dir3/index.htm', $request->server->get('PHP_SELF'));
        $this->assertTrue($request->attributes->get('directory_index'));
    }

    public function testProcessDirectoryWithNoIndex(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/empty/');

        $result = $this->processor->process($request, $config);

        // Should return null (no index file found)
        $this->assertNull($result);
        // PATH_INFO should not be modified (remains at default)
        $this->assertFalse($request->attributes->has('directory_index'));
    }

    public function testProcessDirectoryWithCustomIndexFiles(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'index_files' => ['custom.php', 'index.php'],
        ]);
        $request = Request::create('/dir4/');

        $result = $this->processor->process($request, $config);

        // Should find custom.php first
        $this->assertNull($result);
        $this->assertEquals('/dir4/custom.php', $request->server->get('PHP_SELF'));
    }

    public function testProcessNotDirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/file.php');

        $result = $this->processor->process($request, $config);

        // Should return null (not a directory)
        $this->assertNull($result);
    }

    public function testProcessNonExistentPath(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/nonexistent/');

        $result = $this->processor->process($request, $config);

        // Should return null
        $this->assertNull($result);
    }

    public function testProcessDirectoryListing(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => ['allow_directory_listing' => true],
        ]);
        $request = Request::create('/empty/');

        $result = $this->processor->process($request, $config);

        // Should return a directory listing response
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringContainsString('Index of /empty/', (string) $result->getContent());
        $this->assertStringContainsString('text/html', $result->headers->get('Content-Type') ?? '');
    }

    public function testProcessDirectoryListingDisabled(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => ['allow_directory_listing' => false],
        ]);
        $request = Request::create('/empty/');

        $result = $this->processor->process($request, $config);

        // Should return null when directory listing is disabled
        $this->assertNull($result);
    }

    public function testProcessNestedDirectory(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/nested/deep/');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result);
        $this->assertEquals('/nested/deep/index.php', $request->server->get('PHP_SELF'));
    }

    public function testProcessWithRewrittenPath(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/site1/dir1/');

        // Simulate MultisiteProcessor having rewritten the path
        $request->server->set('PHP_SELF', '/dir1/');

        $result = $this->processor->process($request, $config);

        // Should append index.php to the already rewritten path
        $this->assertNull($result);
        $this->assertEquals('/dir1/index.php', $request->server->get('PHP_SELF'));
    }

    public function testProcessRootDirectory(): void
    {
        // Create index.php in root
        file_put_contents($this->testRoot . '/index.php', '<?php echo "Root";');

        $config = new Configuration(['web_root' => $this->testRoot]);
        $request = Request::create('/');

        $result = $this->processor->process($request, $config);

        $this->assertNull($result);
        $this->assertEquals('/index.php', $request->server->get('PHP_SELF'));
    }

    public function testDirectoryListingParentLink(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => ['allow_directory_listing' => true],
        ]);
        $request = Request::create('/dir1/');

        $result = $this->processor->process($request, $config);

        // Should return null since index.php exists
        $this->assertNull($result);

        // Test with empty directory instead
        $request = Request::create('/empty/');
        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Response::class, $result);
        $content = (string) $result->getContent();
        $this->assertStringContainsString('<a href="/">..</a>', $content); // Parent directory link
    }

    /**
     * Test scandir failure returns forbidden response
     */
    public function testScandirFailureReturnsForbidden(): void
    {
        // Skip this test on systems where scandir throws an error instead of returning false
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('This test is not reliable on PHP < 8.1');
        }

        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => ['allow_directory_listing' => true],
        ]);

        // Create a directory that can't be read
        $unreadableDir = $this->testRoot . '/unreadable';
        mkdir($unreadableDir, 0000, true);

        $request = Request::create('/unreadable/');

        // Use error suppression to handle different PHP versions
        $previousLevel = error_reporting(0);
        $result = $this->processor->process($request, $config);
        error_reporting($previousLevel);

        // Should return 500 Internal Server Error when scandir fails
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(500, $result->getStatusCode());
        $this->assertEquals('Directory listing failed', $result->getContent());

        // Clean up: restore permissions to delete
        chmod($unreadableDir, 0755);
    }

    public function testDirectoryListingWithSpecialCharacters(): void
    {
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'security' => [
                'allow_directory_listing' => true,
            ],
        ]);

        // Create directory and files with special characters
        mkdir($this->testRoot . '/special', 0777, true);
        file_put_contents($this->testRoot . '/special/<test>.html', 'HTML content');
        file_put_contents($this->testRoot . '/special/file&name.txt', 'Text content');

        $request = Request::create('/special/');
        $result = $this->processor->process($request, $config);

        $this->assertInstanceOf(Response::class, $result);
        $content = $result->getContent() ?: '';

        // Check that special characters are properly escaped
        $this->assertStringContainsString('&lt;test&gt;.html', $content);
        $this->assertStringContainsString('file&amp;name.txt', $content);
    }
}
