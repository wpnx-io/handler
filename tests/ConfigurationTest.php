<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Configuration;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $config = new Configuration();

        // Check default values
        $this->assertEquals(getcwd(), $config->get('web_root'));

        // multisite returns array with 'enabled' => false by default
        $multisite = $config->get('multisite');
        $this->assertIsArray($multisite);
        $this->assertFalse($multisite['enabled']);

        // lambda returns array after processing 'auto'
        $lambda = $config->get('lambda');
        $this->assertIsArray($lambda);
    }

    public function testCustomWebRoot(): void
    {
        $config = new Configuration([
            'web_root' => '/custom/path',
        ]);

        $this->assertEquals('/custom/path', $config->get('web_root'));
    }

    public function testSimpleMultisiteConfiguration(): void
    {
        $config = new Configuration([
            'multisite' => true,
        ]);

        $settings = $config->get('multisite');
        $this->assertTrue($settings['enabled']);
        $this->assertEquals('#^/[_0-9a-zA-Z-]+(/wp-.*)#', $settings['pattern']);
        $this->assertEquals('/wp$1', $settings['replacement']);
    }

    public function testDetailedMultisiteConfiguration(): void
    {
        $config = new Configuration([
            'multisite' => [
                'enabled' => true,
                'pattern' => '#custom#',
                'replacement' => 'replaced',
            ],
        ]);

        $settings = $config->get('multisite');
        $this->assertTrue($settings['enabled']);
        $this->assertEquals('#custom#', $settings['pattern']);
        $this->assertEquals('replaced', $settings['replacement']);
    }

    public function testMultisiteDisabled(): void
    {
        $config = new Configuration([
            'multisite' => false,
        ]);

        $settings = $config->get('multisite');
        $this->assertFalse($settings['enabled']);
    }

    public function testSimpleLambdaConfiguration(): void
    {
        $config = new Configuration([
            'lambda' => true,
        ]);

        $settings = $config->get('lambda');
        $this->assertTrue($settings['enabled']);
        $this->assertEquals(['/tmp/uploads', '/tmp/cache', '/tmp/sessions'], $settings['directories']);
    }

    public function testDetailedLambdaConfiguration(): void
    {
        $config = new Configuration([
            'lambda' => [
                'enabled' => false,
                'directories' => ['/custom/tmp'],
            ],
        ]);

        $settings = $config->get('lambda');
        $this->assertFalse($settings['enabled']);
        $this->assertEquals(['/custom/tmp'], $settings['directories']);
    }

    public function testLambdaAutoDetection(): void
    {
        $config = new Configuration();

        // When lambda is not set, it returns empty array (auto-detection mode)
        $lambdaSettings = $config->get('lambda');
        $this->assertIsArray($lambdaSettings);
        $this->assertEmpty($lambdaSettings);
    }

    public function testGetWithInvalidKey(): void
    {
        $config = new Configuration();

        $this->assertNull($config->get('non.existent.key'));
        $this->assertEquals('default', $config->get('non.existent.key', 'default'));
    }

    public function testGetWithEmptyKey(): void
    {
        $config = new Configuration();

        $this->assertNull($config->get(''));
    }

    public function testSecurityConfiguration(): void
    {
        $config = new Configuration([
            'security' => [
                'blocked_patterns' => ['#\.git#'],
                'check_symlinks' => false,
            ],
        ]);

        $this->assertEquals(['#\.git#'], $config->get('security.blocked_patterns'));
        $this->assertFalse($config->get('security.check_symlinks'));
    }

    public function testIndexFilesConfiguration(): void
    {
        $config = new Configuration([
            'index_files' => ['index.html', 'default.php'],
        ]);

        $this->assertEquals(['index.html', 'default.php'], $config->get('index_files'));
    }

    public function testDefaultIndexFiles(): void
    {
        $config = new Configuration();

        $indexFiles = $config->get('index_files');
        $this->assertContains('index.php', $indexFiles);
        $this->assertContains('index.html', $indexFiles);
    }

    public function testMimeTypesConfiguration(): void
    {
        $config = new Configuration([
            'mime_types' => [
                'custom' => 'application/custom',
            ],
        ]);

        $this->assertEquals(['custom' => 'application/custom'], $config->get('mime_types'));
    }

    public function testDefaultMimeType(): void
    {
        $config = new Configuration([
            'default_mime_type' => 'text/plain',
        ]);

        $this->assertEquals('text/plain', $config->get('default_mime_type'));
    }

    public function testImmutability(): void
    {
        $initialConfig = [
            'web_root' => '/test',
            'multisite' => true,
        ];

        $config = new Configuration($initialConfig);

        // Modifying the initial array shouldn't affect the configuration
        $initialConfig['web_root'] = '/changed';

        $this->assertEquals('/test', $config->get('web_root'));
    }

    public function testAllMethod(): void
    {
        $config = new Configuration([
            'web_root' => '/test',
            'multisite' => true,
        ]);

        $all = $config->all();

        // Check that all() returns the complete configuration
        $this->assertIsArray($all);
        $this->assertEquals('/test', $all['web_root']);
        $this->assertArrayHasKey('multisite', $all);
        $this->assertArrayHasKey('lambda', $all);
        $this->assertArrayHasKey('security', $all);
        $this->assertArrayHasKey('wordpress_index', $all);
        $this->assertArrayHasKey('wp_directory', $all);
        $this->assertArrayHasKey('index_files', $all);
    }

    public function testLambdaDisabled(): void
    {
        $config = new Configuration([
            'lambda' => false,
        ]);

        $settings = $config->get('lambda');
        $this->assertFalse($settings['enabled']);
    }

    public function testWordPressIndexConfiguration(): void
    {
        $config = new Configuration([
            'wordpress_index' => '/custom/index.php',
        ]);

        $this->assertEquals('/custom/index.php', $config->get('wordpress_index'));
    }

    public function testNestedConfigurationAccess(): void
    {
        $config = new Configuration([
            'deeply' => [
                'nested' => [
                    'value' => 'found',
                    'array' => ['item1', 'item2'],
                ],
            ],
        ]);

        $this->assertEquals('found', $config->get('deeply.nested.value'));
        $this->assertEquals(['item1', 'item2'], $config->get('deeply.nested.array'));
        $this->assertNull($config->get('deeply.nested.nonexistent'));
        $this->assertEquals('default', $config->get('deeply.nested.nonexistent', 'default'));
    }
}
