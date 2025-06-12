<?php

declare(strict_types=1);

namespace WpNx\Handler\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use WpNx\Handler\Exceptions\SecurityException;

class SecurityExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $message = 'Access denied to restricted path';
        $exception = new SecurityException($message);

        $this->assertInstanceOf(SecurityException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(403, $exception->getCode());
    }

    public function testWithEmptyMessage(): void
    {
        $exception = new SecurityException('');

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(403, $exception->getCode());
    }

    public function testHttpResponseStatusCode(): void
    {
        $exception = new SecurityException('Forbidden');

        // The code should be 403 (Forbidden)
        $this->assertEquals(403, $exception->getCode());
    }
}
