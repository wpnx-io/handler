<?php

declare(strict_types=1);

namespace WpNx\Handler;

/**
 * Configuration management for the handler
 *
 * Supports both simple (true/false) and detailed configuration modes.
 */
class Configuration
{
    private array $config;

    /**
     * Create a new configuration instance
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        // Set default web_root if not provided
        if (!isset($config['web_root'])) {
            $config['web_root'] = getcwd();
        }

        $this->config = $this->normalizeConfig($config);
    }

    /**
     * Normalize configuration by expanding simple settings to detailed ones
     */
    private function normalizeConfig(array $config): array
    {
        // Normalize Lambda configuration
        if (isset($config['lambda'])) {
            if ($config['lambda'] === true) {
                // Simple mode: force Lambda on
                $config['lambda'] = [
                    'enabled' => true,
                    'directories' => ['/tmp/uploads', '/tmp/cache', '/tmp/sessions'],
                ];
            } elseif ($config['lambda'] === false) {
                // Simple mode: force Lambda off
                $config['lambda'] = ['enabled' => false];
            }
            // Array configuration is used as-is
        } else {
            // Not set: will auto-detect
            $config['lambda'] = [];
        }

        // Normalize Multisite configuration
        if (isset($config['multisite'])) {
            if ($config['multisite'] === true) {
                // Simple mode: use default multisite configuration
                $config['multisite'] = [
                    'enabled' => true,
                    'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
                    'replacement' => '/wp$1'
                ];
            } elseif ($config['multisite'] === false) {
                // Simple mode: disable multisite
                $config['multisite'] = ['enabled' => false];
            }
            // Array configuration is used as-is
        } else {
            // Default: multisite disabled
            $config['multisite'] = ['enabled' => false];
        }

        // Security configuration
        $defaultSecurity = [
            'allow_directory_listing' => false,
            'check_symlinks' => true,
            'blocked_patterns' => [
                '/\.git/',
                '/\.env/',
                '/\.htaccess/',
                '/composer\.(json|lock)/',
                '/wp-config\.php/',
                '/readme\.(txt|html|md)/i',
            ],
        ];

        if (isset($config['security'])) {
            $config['security'] = array_merge($defaultSecurity, $config['security']);
        } else {
            $config['security'] = $defaultSecurity;
        }

        // Other defaults
        $defaults = [
            'wordpress_index' => '/index.php',
            'wp_directory' => '/wp',
            'index_files' => ['index.php', 'index.html', 'index.htm'],
        ];

        return array_merge($defaults, $config);
    }

    /**
     * Get a configuration value by key
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key not found
     * @return mixed The configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get all configuration values
     *
     * @return array All configuration values
     */
    public function all(): array
    {
        return $this->config;
    }
}
