<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testBasic(): void
    {
        $this->assertSame(1, 1);
    }
}