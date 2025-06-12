<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Handler;
use WpNx\Handler\Configuration;
use Symfony\Component\HttpFoundation\Request;

class HandlerTest extends TestCase
{
    private string $testRoot;
    private int $initialOutputBufferLevel;
    private Configuration $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Record initial output buffer level
        $this->initialOutputBufferLevel = ob_get_level();

        $this->testRoot = sys_get_temp_dir() . '/wpnx-handler-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create test files
        file_put_contents($this->testRoot . '/index.php', '<?php echo "Hello World";');
        file_put_contents($this->testRoot . '/test.html', '<html><body>Test</body></html>');
        file_put_contents($this->testRoot . '/style.css', 'body { color: red; }');
        file_put_contents($this->testRoot . '/script.js', 'console.log("test");');

        // Create directory with index file
        mkdir($this->testRoot . '/subdir', 0777, true);
        file_put_contents($this->testRoot . '/subdir/index.php', '<?php echo "Subdir";');

        // Create wp directory structure for multisite
        mkdir($this->testRoot . '/wp-content', 0777, true);
        mkdir($this->testRoot . '/wp', 0777, true);
        mkdir($this->testRoot . '/wp/wp-admin', 0777, true);
        file_put_contents($this->testRoot . '/index.php', '<?php echo "WordPress Root";');
        file_put_contents($this->testRoot . '/wp/wp-admin/index.php', '<?php echo "Admin Panel";');

        // Set up $_SERVER globals
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        // Initialize configuration
        $this->config = new Configuration(['web_root' => $this->testRoot]);
    }

    protected function tearDown(): void
    {
        // Restore to initial output buffer level
        while (ob_get_level() > $this->initialOutputBufferLevel) {
            ob_end_clean();
        }
        while (ob_get_level() < $this->initialOutputBufferLevel) {
            ob_start();
        }

        parent::tearDown();

        $this->removeDirectory($this->testRoot);

        // Clean up globals
        unset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_METHOD']);
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

    public function testConstructorWithDefaultConfiguration(): void
    {
        $handler = new Handler();
        $this->assertInstanceOf(Handler::class, $handler);
    }

    public function testConstructorWithCustomConfiguration(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);
        $this->assertInstanceOf(Handler::class, $handler);
    }


    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRunMethod(): void
    {
        // This test is tricky because run() sends output directly
        // We'll just verify it doesn't throw an exception
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Override REQUEST_URI for this test
        $_SERVER['REQUEST_URI'] = '/test.html';

        ob_start();
        $handler->run();
        $output = ob_get_clean() ?: '';

        $this->assertIsString($output);
        $this->assertStringContainsString('Test', $output);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider requestPatternProvider
     */
    public function testRequestPatterns(string $requestUri, string $expectedOutput, string $description): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Create request
        $request = Request::create($requestUri);

        ob_start();
        $result = $handler->run($request);
        if ($result) {
            include $result;
        }
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString(
            $expectedOutput,
            $output,
            "Failed for $description: $requestUri"
        );
    }

    public static function requestPatternProvider(): array
    {
        return [
            // Basic file access
            ['/', 'WordPress Root', 'Root index.php'],
            ['/index.php', 'WordPress Root', 'Direct index.php'],
            ['/test.html', 'Test', 'Static HTML file'],
            ['/style.css', 'body { color: red; }', 'Static CSS file'],

            // Directory access
            ['/subdir/', 'Subdir', 'Directory with index.php'],
            ['/subdir/index.php', 'Subdir', 'Direct subdir index.php'],

            // WordPress admin access
            ['/wp/wp-admin/', 'Admin Panel', 'Admin directory'],
            ['/wp/wp-admin/index.php', 'Admin Panel', 'Direct admin index.php'],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAdminPanelAccess(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Test admin panel access
        $request = Request::create('https://example.com/wp/wp-admin/');

        ob_start();
        $result = $handler->run($request);
        if ($result) {
            include $result;
        }
        $output = ob_get_clean() ?: '';


        $this->assertStringContainsString(
            'Admin Panel',
            $output,
            'Admin panel should be displayed for /wp/wp-admin/'
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testServerVariableChanges(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Test cases to verify $_SERVER changes
        $testCases = [
            'Root index' => [
                'url' => '/',
                'expected' => [
                    'PHP_SELF' => '/index.php',
                    'SCRIPT_NAME' => '/index.php',
                    'SCRIPT_FILENAME' => $this->testRoot . '/index.php',
                ],
            ],
            'Directory with trailing slash' => [
                'url' => '/subdir/',
                'expected' => [
                    'PHP_SELF' => '/subdir/index.php',
                    'SCRIPT_NAME' => '/subdir/index.php',
                    'SCRIPT_FILENAME' => $this->testRoot . '/subdir/index.php',
                ],
            ],
            'Admin directory' => [
                'url' => '/wp/wp-admin/',
                'expected' => [
                    'PHP_SELF' => '/wp/wp-admin/index.php',
                    'SCRIPT_NAME' => '/wp/wp-admin/index.php',
                    'SCRIPT_FILENAME' => $this->testRoot . '/wp/wp-admin/index.php',
                ],
            ],
            'Direct PHP file' => [
                'url' => '/wp/wp-admin/index.php',
                'expected' => [
                    'PHP_SELF' => '/wp/wp-admin/index.php',
                    'SCRIPT_NAME' => '/wp/wp-admin/index.php',
                    'SCRIPT_FILENAME' => $this->testRoot . '/wp/wp-admin/index.php',
                ],
            ],
            'Non-existent file' => [
                'url' => '/nonexistent/page',
                'expected' => [
                    'PHP_SELF' => '/index.php',
                    'SCRIPT_NAME' => '/index.php',
                    'SCRIPT_FILENAME' => $this->testRoot . '/index.php',
                ],
            ],
        ];

        foreach ($testCases as $description => $testCase) {
            $request = Request::create($testCase['url']);

            // Clear $_SERVER to ensure clean state
            unset($_SERVER['PHP_SELF'], $_SERVER['SCRIPT_NAME'], $_SERVER['SCRIPT_FILENAME']);

            $result = $handler->run($request);

            foreach ($testCase['expected'] as $key => $expectedValue) {
                $this->assertEquals(
                    $expectedValue,
                    $_SERVER[$key] ?? null,
                    sprintf(
                        '%s: $_SERVER[%s] should be \'%s\' but got \'%s\'',
                        $description,
                        $key,
                        $expectedValue,
                        $_SERVER[$key] ?? 'null'
                    )
                );
            }

            $this->assertNotNull($result, "$description: Handler should return a file path");
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAddCustomProcessor(): void
    {
        // Create test environment
        mkdir($this->testRoot . '/custom', 0777, true);
        file_put_contents($this->testRoot . '/custom/test.php', '<?php echo "Custom";');

        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Create a custom processor
        $customProcessor = new class implements \WpNx\Handler\Processors\ProcessorInterface {
            public function process(
                \Symfony\Component\HttpFoundation\Request $request,
                \WpNx\Handler\Configuration $config
            ): \Symfony\Component\HttpFoundation\Request|\Symfony\Component\HttpFoundation\Response|null {
                if ($request->getPathInfo() === '/custom/test.php') {
                    return new \Symfony\Component\HttpFoundation\Response('Custom Response', 200);
                }
                return null;
            }
        };

        // Add the custom processor with high priority (0 = first)
        $handler->addProcessor($customProcessor, 0);

        // Test that the custom processor handles the request
        $request = Request::create('/custom/test.php');

        ob_start();
        $result = $handler->run($request);
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('Custom Response', $output);
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHandlerExceptionHandling(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Create a processor that throws an exception
        $exceptionProcessor = new class implements \WpNx\Handler\Processors\ProcessorInterface {
            public function process(
                \Symfony\Component\HttpFoundation\Request $request,
                \WpNx\Handler\Configuration $config
            ): \Symfony\Component\HttpFoundation\Request|\Symfony\Component\HttpFoundation\Response|null {
                throw new \Exception('Test exception');
            }
        };

        $handler->addProcessor($exceptionProcessor, 0);

        $request = Request::create('/test');

        // Suppress error logging for this test
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        ob_start();
        $result = $handler->run($request);
        $output = ob_get_clean() ?: '';

        // Restore error logging
        if ($originalErrorLog !== false) {
            ini_set('error_log', $originalErrorLog);
        }

        $this->assertStringContainsString('Internal Server Error', $output);
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFileNotFoundExceptionHandling(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Create a processor that throws FileNotFoundException
        $notFoundProcessor = new class implements \WpNx\Handler\Processors\ProcessorInterface {
            public function process(
                \Symfony\Component\HttpFoundation\Request $request,
                \WpNx\Handler\Configuration $config
            ): \Symfony\Component\HttpFoundation\Request|\Symfony\Component\HttpFoundation\Response|null {
                throw new \WpNx\Handler\Exceptions\FileNotFoundException('File not found');
            }
        };

        $handler->addProcessor($notFoundProcessor, 0);

        $request = Request::create('/missing');

        ob_start();
        $result = $handler->run($request);
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('File not found', $output);
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHandlerWithNoFileToExecute(): void
    {
        // Create index.php to satisfy WordPressProcessor
        file_put_contents($this->testRoot . '/index.php', '<?php echo "Index";');

        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Request for a non-existent path - WordPressProcessor will handle it
        $request = Request::create('/this/does/not/exist');

        ob_start();
        $result = $handler->run($request);
        $output = ob_get_clean() ?: '';

        // WordPressProcessor will return the index.php file path
        $this->assertNotNull($result);
        $this->assertEquals($this->testRoot . '/index.php', $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHandlerWithSecurityException(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Create a processor that throws SecurityException
        $securityProcessor = new class implements \WpNx\Handler\Processors\ProcessorInterface {
            public function process(
                \Symfony\Component\HttpFoundation\Request $request,
                \WpNx\Handler\Configuration $config
            ): \Symfony\Component\HttpFoundation\Request|\Symfony\Component\HttpFoundation\Response|null {
                throw new \WpNx\Handler\Exceptions\SecurityException('Access denied');
            }
        };

        $handler->addProcessor($securityProcessor, 0);

        $request = Request::create('/secure');

        ob_start();
        $result = $handler->run($request);
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('Access denied', $output);
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHandlerWithDefaultConfiguration(): void
    {
        // Create a temporary index.php in current directory for test
        $tempIndex = getcwd() . '/index.php';
        file_put_contents($tempIndex, '<?php echo "Test";');

        try {
            // Test Handler with no configuration passed (uses defaults)
            $handler = new Handler();

            // Should use current working directory as web root
            $request = Request::create('/');

            ob_start();
            $result = $handler->run($request);
            $output = ob_get_clean() ?: '';

            // Should handle the request (either return a file path or send a response)
            $this->assertTrue($result !== null || strlen($output) > 0);
        } finally {
            // Clean up
            if (file_exists($tempIndex)) {
                unlink($tempIndex);
            }
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAddProcessorWithDifferentPriorities(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Create a processor that returns a specific response
        $testProcessor = new class implements \WpNx\Handler\Processors\ProcessorInterface {
            public function process(
                \Symfony\Component\HttpFoundation\Request $request,
                \WpNx\Handler\Configuration $config
            ): \Symfony\Component\HttpFoundation\Request|\Symfony\Component\HttpFoundation\Response|null {
                // Return response only for our test path
                if ($request->getPathInfo() === '/test') {
                    return new \Symfony\Component\HttpFoundation\Response('Test Response');
                }
                return null;
            }
        };

        // Add processor with priority 0 (will be first)
        $handler->addProcessor($testProcessor, 0);

        $request = Request::create('/test');

        ob_start();
        $result = $handler->run($request);
        $output = ob_get_clean() ?: '';

        // Verify the custom processor was called and handled the request
        $this->assertStringContainsString('Test Response', $output);
        $this->assertNull($result);
    }

    public function testProcessorChainForAdminPanel(): void
    {
        $config = new Configuration(['web_root' => $this->testRoot]);
        $handler = new Handler($config);

        // Test with detailed logging
        $request = Request::create('https://example.com/wp/wp-admin/');

        // Store initial state
        $initialState = [
            'PHP_SELF' => $request->server->get('PHP_SELF'),
            'SCRIPT_NAME' => $request->server->get('SCRIPT_NAME'),
            'SCRIPT_FILENAME' => $request->server->get('SCRIPT_FILENAME'),
            'REQUEST_URI' => $request->server->get('REQUEST_URI'),
        ];

        $result = $handler->run($request);

        // Get final state
        $finalState = [
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
            'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? null,
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        ];

        // Assert final state
        $this->assertEquals('/wp/wp-admin/index.php', $_SERVER['PHP_SELF']);
        $this->assertEquals('/wp/wp-admin/index.php', $_SERVER['SCRIPT_NAME']);
        $this->assertEquals($this->testRoot . '/wp/wp-admin/index.php', $_SERVER['SCRIPT_FILENAME']);
        $this->assertEquals($this->testRoot . '/wp/wp-admin/index.php', $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRealWorldScenario(): void
    {
        // Simulate real-world scenario where handler.php is in web directory
        $webRoot = '/Users/tsuyoshi/dev/lmaga/lmaga/web'; // Points to actual web directory

        // Skip test if web directory doesn't exist
        if (!is_dir($webRoot)) {
            $this->markTestSkipped('Web directory not found at: ' . $webRoot);
        }

        $config = new Configuration([
            'web_root' => $webRoot,
            'multisite' => true,
        ]);

        $handler = new Handler($config);

        // Test admin panel access
        $request = Request::create('https://example.com/wp/wp-admin/');
        $result = $handler->run($request);

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertEquals($webRoot . '/wp/wp-admin/index.php', $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMultisiteAdminAccess(): void
    {
        // Enable multisite configuration
        $config = new Configuration([
            'web_root' => $this->testRoot,
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/([_0-9a-zA-Z-]+)/(wp-admin/?)#',
                'replacement' => '/wp/$2',
            ],
        ]);

        $handler = new Handler($config);

        // Test multisite admin access
        $request = Request::create('https://example.com/sitename/wp-admin/');

        ob_start();
        $result = $handler->run($request);
        if ($result) {
            include $result;
        }
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString(
            'Admin Panel',
            $output,
            'Admin panel should be displayed for multisite /sitename/wp-admin/'
        );
    }

    /**
     * Test handler returns 404 when no processor handles the request
     */
    public function testHandlerReturns404WhenNoProcessorHandlesRequest(): void
    {
        // Create a handler with empty processor chain
        $handler = new Handler($this->config);

        // Use reflection to clear processors
        $reflection = new \ReflectionClass($handler);
        $processorsProperty = $reflection->getProperty('processors');
        $processorsProperty->setAccessible(true);
        $processorsProperty->setValue($handler, []);

        // Create request for non-existent path
        $request = Request::create('/non-existent-path');

        ob_start();
        $result = $handler->run($request);
        $output = ob_get_clean();

        $this->assertNull($result);
        $this->assertStringContainsString('Not Found', (string) $output);
    }

    /**
     * Test preparePhpEnvironment returns null when SCRIPT_FILENAME is not set
     */
    public function testPreparePhpEnvironmentReturnsNullWhenScriptFilenameNotSet(): void
    {
        $handler = new Handler($this->config);
        $request = Request::create('/test');

        // Don't set SCRIPT_FILENAME
        $request->server->remove('SCRIPT_FILENAME');

        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('preparePhpEnvironment');
        $method->setAccessible(true);

        $result = $method->invoke($handler, $request);
        $this->assertNull($result);
    }
}
