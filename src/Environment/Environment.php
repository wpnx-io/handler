<?php

declare(strict_types=1);

namespace WpNx\Handler\Environment;

use WpNx\Handler\Configuration;
use WpNx\Handler\Environment\EnvironmentInterface;

/**
 * Environment management
 *
 * Handles environment detection and setup for various platforms including AWS Lambda.
 */
class Environment implements EnvironmentInterface
{
    private Configuration $config;
    private string $platform;
    private array $platformInfo = [];

    public function __construct(Configuration $config)
    {
        $this->config = $config;
        $this->detectPlatform();
    }

    /**
     * Setup the environment based on detected platform
     */
    public function setup(): void
    {
        switch ($this->platform) {
            case 'lambda':
                $this->setupLambda();
                break;
            // Future platforms can be added here
            // case 'kubernetes':
            //     $this->setupKubernetes();
            //     break;
            default:
                // Standard environment doesn't need special setup
                break;
        }
    }

    /**
     * Check if running in Lambda environment
     */
    public function isLambda(): bool
    {
        return $this->platform === 'lambda';
    }

    /**
     * Get environment information
     */
    public function getInfo(): array
    {
        $info = [
            'platform' => $this->platform,
        ];

        // Include platform-specific info only when relevant
        if ($this->platform === 'lambda') {
            $info['lambda'] = $this->platformInfo;
        }

        return $info;
    }

    /**
     * Detect the current platform
     */
    private function detectPlatform(): void
    {
        // Check configuration first
        $lambdaConfig = $this->config->get('lambda', []);

        if (isset($lambdaConfig['enabled'])) {
            $this->platform = $lambdaConfig['enabled'] ? 'lambda' : 'standard';
        } else {
            // Auto-detect platform
            $this->platform = $this->autoDetectPlatform();
        }

        // Collect platform-specific information
        if ($this->platform === 'lambda') {
            $this->platformInfo = $this->collectLambdaInfo();
        }
    }

    /**
     * Auto-detect the platform from environment
     */
    private function autoDetectPlatform(): string
    {
        // Lambda detection
        $isLambda = isset($_ENV['AWS_LAMBDA_FUNCTION_NAME']) ||
                    isset($_ENV['LAMBDA_TASK_ROOT']) ||
                    isset($_ENV['_HANDLER']);

        if ($isLambda) {
            return 'lambda';
        }

        // Future platform detection can be added here
        // if (isset($_ENV['KUBERNETES_SERVICE_HOST'])) return 'kubernetes';
        // if (file_exists('/.dockerenv')) return 'docker';
        // if (isset($_ENV['GOOGLE_CLOUD_PROJECT'])) return 'cloudrun';

        return 'standard';
    }

    /**
     * Collect Lambda-specific information
     */
    private function collectLambdaInfo(): array
    {
        $info = [];

        if (isset($_ENV['AWS_LAMBDA_FUNCTION_NAME'])) {
            $info['function_name'] = $_ENV['AWS_LAMBDA_FUNCTION_NAME'];
        }

        if (isset($_ENV['LAMBDA_TASK_ROOT'])) {
            $info['task_root'] = $_ENV['LAMBDA_TASK_ROOT'];
        }

        if (isset($_ENV['AWS_REGION'])) {
            $info['region'] = $_ENV['AWS_REGION'];
        } elseif (isset($_ENV['AWS_DEFAULT_REGION'])) {
            $info['region'] = $_ENV['AWS_DEFAULT_REGION'];
        }

        if (isset($_ENV['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'])) {
            $info['memory_limit'] = (int) $_ENV['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'];
        }

        if (isset($_ENV['AWS_LAMBDA_FUNCTION_VERSION'])) {
            $info['function_version'] = $_ENV['AWS_LAMBDA_FUNCTION_VERSION'];
        }

        if (isset($_ENV['AWS_LAMBDA_LOG_GROUP_NAME'])) {
            $info['log_group'] = $_ENV['AWS_LAMBDA_LOG_GROUP_NAME'];
        }

        if (isset($_ENV['AWS_LAMBDA_LOG_STREAM_NAME'])) {
            $info['log_stream'] = $_ENV['AWS_LAMBDA_LOG_STREAM_NAME'];
        }

        return $info;
    }

    /**
     * Setup Lambda-specific environment
     */
    private function setupLambda(): void
    {
        // Default Lambda directories
        $defaultDirs = [
            '/tmp/uploads',
            '/tmp/cache',
            '/tmp/sessions',
        ];

        // Use custom directories if provided, otherwise use defaults
        $dirs = $this->config->get('lambda.directories', $defaultDirs);

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }
    }
}
