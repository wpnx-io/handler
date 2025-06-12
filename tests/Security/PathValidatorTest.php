<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Security;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Security\PathValidator;
use WpNx\Handler\Exceptions\SecurityException;

class PathValidatorTest extends TestCase
{
    private PathValidator $validator;
    private string $testRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir() . '/path-validator-test-' . uniqid();
        mkdir($this->testRoot, 0777, true);

        // Create test directory structure
        mkdir($this->testRoot . '/allowed', 0777, true);
        mkdir($this->testRoot . '/allowed/subdir', 0777, true);
        file_put_contents($this->testRoot . '/allowed/test.txt', 'test');

        $this->validator = new PathValidator($this->testRoot);
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

    public function testValidateSafePath(): void
    {
        $path = $this->validator->validate('/allowed/test.txt');
        $this->assertEquals('/allowed/test.txt', $path);
    }

    public function testValidateFilePathWithinRoot(): void
    {
        $filePath = $this->testRoot . '/allowed/test.txt';
        $validated = $this->validator->validateFilePath($filePath);
        // The validator returns the realpath, which may differ on macOS
        $this->assertEquals(realpath($filePath), $validated);
    }

    public function testValidateFilePathOutsideRoot(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path outside web root');

        $this->validator->validateFilePath('/etc/passwd');
    }

    /**
     * @dataProvider pathTraversalProvider
     */
    public function testPathTraversalPrevention(string $maliciousPath): void
    {
        $this->expectException(SecurityException::class);

        $this->validator->validate($maliciousPath);
    }

    public static function pathTraversalProvider(): array
    {
        return [
            'simple traversal' => ['../'],
            'double traversal' => ['../../'],
            'hidden traversal' => ['/allowed/../../'],
            'complex traversal' => ['/allowed/../../../etc/passwd'],
            'encoded traversal' => ['%2e%2e/'],
            'double encoded' => ['%252e%252e%252f'],
            'windows traversal' => ['..\\..\\'],
            'mixed separators' => ['../..\\'],
        ];
    }

    public function testNullByteDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Null byte detected in path');

        $this->validator->validate("/test\0.php");
    }

    public function testInvalidCharacterDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid characters in path');

        $this->validator->validate("/test\x01file.php");
    }

    public function testIsHiddenPath(): void
    {
        $this->assertTrue($this->validator->isHiddenPath('/.hidden'));
        $this->assertTrue($this->validator->isHiddenPath('/path/.hidden/file'));
        $this->assertTrue($this->validator->isHiddenPath('/.git/config'));

        $this->assertFalse($this->validator->isHiddenPath('/visible'));
        $this->assertFalse($this->validator->isHiddenPath('/path/to/file'));
        $this->assertFalse($this->validator->isHiddenPath('/./relative'));
        $this->assertFalse($this->validator->isHiddenPath('/../parent'));
    }

    public function testValidateFilePathNonExistent(): void
    {
        // Create parent directory
        $parentDir = $this->testRoot . '/new';
        mkdir($parentDir, 0777, true);

        $nonExistentFile = $parentDir . '/file.txt';
        $validated = $this->validator->validateFilePath($nonExistentFile);

        $this->assertEquals($nonExistentFile, $validated);

        rmdir($parentDir);
    }

    public function testConstructorWithInvalidWebRoot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid web root path');

        new PathValidator('/non/existent/path');
    }

    public function testNullByteInPath(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Null byte detected in path');

        $this->validator->validate("/test\0file.php");
    }

    public function testNullByteInMiddleOfPath(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Null byte detected in path');

        $this->validator->validate("/directory/file\0.txt");
    }

    public function testInvalidCharactersInPath(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid characters in path');

        // Test with control characters
        $this->validator->validate("/test\x01file.php");
    }

    public function testValidateFilePathWithoutSymlinkCheck(): void
    {
        // Create validator with symlink checking disabled
        $validator = new PathValidator($this->testRoot, false);

        // Use realpath for testRoot to ensure it matches the validator's normalized web root
        $filePath = realpath($this->testRoot) . '/allowed/test.txt';
        $validated = $validator->validateFilePath($filePath);

        // Should return the original path, not realpath
        $this->assertEquals($filePath, $validated);
    }

    public function testValidateFilePathOutsideRootWithoutSymlinkCheck(): void
    {
        $validator = new PathValidator($this->testRoot, false);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path outside web root');

        $validator->validateFilePath('/etc/passwd');
    }

    public function testValidateFilePathNonExistentWithoutSymlinkCheck(): void
    {
        $validator = new PathValidator($this->testRoot, false);

        // Use realpath for testRoot to ensure it matches the validator's normalized web root
        $filePath = realpath($this->testRoot) . '/allowed/nonexistent.txt';
        $validated = $validator->validateFilePath($filePath);

        $this->assertEquals($filePath, $validated);
    }

    public function testIsHiddenPathEdgeCases(): void
    {
        // Empty path
        $this->assertFalse($this->validator->isHiddenPath(''));

        // Root path
        $this->assertFalse($this->validator->isHiddenPath('/'));

        // Current directory
        $this->assertFalse($this->validator->isHiddenPath('.'));

        // Parent directory
        $this->assertFalse($this->validator->isHiddenPath('..'));

        // Path with only dots
        $this->assertFalse($this->validator->isHiddenPath('/./.'));
        $this->assertFalse($this->validator->isHiddenPath('/../..'));

        // Multiple hidden directories
        $this->assertTrue($this->validator->isHiddenPath('/.hidden1/.hidden2/file'));

        // Hidden file at root
        $this->assertTrue($this->validator->isHiddenPath('.env'));
        $this->assertTrue($this->validator->isHiddenPath('.htaccess'));
    }

    public function testValidationWithControlCharacters(): void
    {
        $controlChars = [
            "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
            "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
            "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
            "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F",
            "\x7F",
        ];

        foreach ($controlChars as $char) {
            try {
                $this->validator->validate("/test{$char}file.php");
                $this->fail("Expected SecurityException for control character: " . ord($char));
            } catch (SecurityException $e) {
                // Null byte has a specific message
                if ($char === "\x00") {
                    $this->assertStringContainsString('Null byte detected in path', $e->getMessage());
                } else {
                    $this->assertStringContainsString('Invalid characters in path', $e->getMessage());
                }
            }
        }
    }

    public function testValidPathsWithSpecialCharacters(): void
    {
        // These should NOT throw exceptions
        $validPaths = [
            '/path/with-dashes',
            '/path_with_underscores',
            '/path/with spaces',
            '/path/with.dots',
            '/path/with(parens)',
            '/path/with[brackets]',
            '/path/with{braces}',
            '/path/with@symbol',
            '/path/with#hash',
            '/path/with$dollar',
            '/path/with%percent',
            '/path/with&ampersand',
            '/path/with+plus',
            '/path/with=equals',
        ];

        foreach ($validPaths as $path) {
            $result = $this->validator->validate($path);
            $this->assertEquals($path, $result);
        }
    }

    /**
     * Test validateFilePath when parent directory doesn't exist
     */
    public function testValidateFilePathWithNonExistentParentDirectory(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path outside web root');

        // Try to validate a file path where the parent directory doesn't exist
        $nonExistentPath = $this->testRoot . '/nonexistent/parent/file.txt';
        $this->validator->validateFilePath($nonExistentPath);
    }
}
