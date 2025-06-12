<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Exceptions\FileNotFoundException;

class FileNotFoundExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $path = '/path/to/missing/file.txt';
        $exception = new FileNotFoundException($path);

        $this->assertInstanceOf(FileNotFoundException::class, $exception);
        $this->assertEquals("File not found: $path", $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testWithEmptyPath(): void
    {
        $exception = new FileNotFoundException('');

        $this->assertEquals("File not found: ", $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testWithSpecialCharactersInPath(): void
    {
        $path = '/path/with spaces/and-special@chars#.txt';
        $exception = new FileNotFoundException($path);

        $this->assertEquals("File not found: $path", $exception->getMessage());
    }
}
