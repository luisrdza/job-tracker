<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{

    #[PHPUnit\Framework\Attributes\CoversNothing]
    public function testBasic(): void
    {
        $this->assertSame(1, 1);
    }
}