<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Environment;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Environment\Environment;
use WpNx\Handler\Configuration;

class EnvironmentTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Save original environment
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Restore original environment
        $_ENV = $this->originalEnv;

        // Clean up test directories
        $testDirs = ['/tmp/uploads', '/tmp/cache', '/tmp/sessions', '/tmp/test-custom'];
        foreach ($testDirs as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }
    }

    public function testPlatformDetectionWithLambdaDisabled(): void
    {
        // Even with Lambda environment variables, should be standard when explicitly disabled
        $_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';

        $config = new Configuration(['lambda' => ['enabled' => false]]);
        $env = new Environment($config);

        $this->assertFalse($env->isLambda());

        $info = $env->getInfo();
        $this->assertEquals('standard', $info['platform']);
        $this->assertArrayNotHasKey('lambda', $info);
    }

    public function testPlatformDetectionWithLambdaEnabled(): void
    {
        $config = new Configuration(['lambda' => ['enabled' => true]]);
        $env = new Environment($config);

        $this->assertTrue($env->isLambda());

        $info = $env->getInfo();
        $this->assertEquals('lambda', $info['platform']);
        // Lambda info might be empty if no environment variables are set
        $this->assertArrayHasKey('lambda', $info);
    }

    public function testPlatformAutoDetectionWithLambdaEnvironment(): void
    {
        // Set Lambda environment variables
        $_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';
        $_ENV['AWS_REGION'] = 'us-east-1';
        $_ENV['LAMBDA_TASK_ROOT'] = '/var/task';
        $_ENV['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] = '512';
        $_ENV['AWS_LAMBDA_FUNCTION_VERSION'] = '$LATEST';
        $_ENV['AWS_LAMBDA_LOG_GROUP_NAME'] = '/aws/lambda/test-function';
        $_ENV['AWS_LAMBDA_LOG_STREAM_NAME'] = '2023/01/01/[$LATEST]abc123';

        // No lambda config, should auto-detect
        $config = new Configuration();
        $env = new Environment($config);

        $this->assertTrue($env->isLambda());

        $info = $env->getInfo();
        $this->assertEquals('lambda', $info['platform']);
        $this->assertArrayHasKey('lambda', $info);

        // Check Lambda info
        $lambdaInfo = $info['lambda'];
        $this->assertEquals('test-function', $lambdaInfo['function_name']);
        $this->assertEquals('/var/task', $lambdaInfo['task_root']);
        $this->assertEquals('us-east-1', $lambdaInfo['region']);
        $this->assertEquals(512, $lambdaInfo['memory_limit']);
        $this->assertEquals('$LATEST', $lambdaInfo['function_version']);
        $this->assertEquals('/aws/lambda/test-function', $lambdaInfo['log_group']);
        $this->assertEquals('2023/01/01/[$LATEST]abc123', $lambdaInfo['log_stream']);
    }

    public function testPlatformAutoDetectionWithAlternativeLambdaVariables(): void
    {
        // Test with _HANDLER environment variable
        $_ENV['_HANDLER'] = 'index.handler';

        $config = new Configuration();
        $env = new Environment($config);

        $this->assertTrue($env->isLambda());

        $info = $env->getInfo();
        $this->assertEquals('lambda', $info['platform']);
    }

    public function testPlatformAutoDetectionWithoutLambdaEnvironment(): void
    {
        // Remove all Lambda environment variables
        unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
        unset($_ENV['LAMBDA_TASK_ROOT']);
        unset($_ENV['_HANDLER']);

        $config = new Configuration();
        $env = new Environment($config);

        $this->assertFalse($env->isLambda());

        $info = $env->getInfo();
        $this->assertEquals('standard', $info['platform']);
        $this->assertArrayNotHasKey('lambda', $info);
    }

    public function testLambdaInfoWithRegionFallback(): void
    {
        $_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';
        unset($_ENV['AWS_REGION']);
        $_ENV['AWS_DEFAULT_REGION'] = 'eu-west-1';

        $config = new Configuration(['lambda' => ['enabled' => true]]);
        $env = new Environment($config);

        $info = $env->getInfo();
        $this->assertEquals('eu-west-1', $info['lambda']['region']);
    }

    public function testLambdaSetupCreatesDefaultDirectories(): void
    {
        $config = new Configuration(['lambda' => ['enabled' => true]]);
        $env = new Environment($config);

        // Ensure directories don't exist
        $defaultDirs = ['/tmp/uploads', '/tmp/cache', '/tmp/sessions'];
        foreach ($defaultDirs as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        $env->setup();

        // Check that directories were created
        foreach ($defaultDirs as $dir) {
            $this->assertDirectoryExists($dir);
        }
    }

    public function testLambdaSetupWithCustomDirectories(): void
    {
        $customDir = '/tmp/test-custom';
        if (is_dir($customDir)) {
            rmdir($customDir);
        }

        $config = new Configuration([
            'lambda' => [
                'enabled' => true,
                'directories' => [$customDir],
            ],
        ]);

        $env = new Environment($config);
        $env->setup();

        $this->assertDirectoryExists($customDir);
    }

    public function testStandardPlatformSetupDoesNothing(): void
    {
        $config = new Configuration(['lambda' => ['enabled' => false]]);
        $env = new Environment($config);

        // Should not throw any exceptions
        $env->setup();

        // Verify platform is standard
        $this->assertFalse($env->isLambda());

        $info = $env->getInfo();
        $this->assertEquals('standard', $info['platform']);
    }

    public function testGetInfoWithEmptyLambdaInfo(): void
    {
        // No Lambda environment variables set
        unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
        unset($_ENV['LAMBDA_TASK_ROOT']);
        unset($_ENV['AWS_REGION']);

        $config = new Configuration(['lambda' => ['enabled' => true]]);
        $env = new Environment($config);

        $info = $env->getInfo();
        $this->assertEquals('lambda', $info['platform']);
        // Even with empty lambda info, the key should still be present when platform is lambda
        $this->assertArrayHasKey('lambda', $info);
        $this->assertIsArray($info['lambda']);
    }
}
