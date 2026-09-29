<?php

declare(strict_types=1);

namespace Tests\Clock;

use PHPUnit\Framework\TestCase;

final class ContainerClockTest extends TestCase
{
    public function testPracticeClockStaysInOctober2026(): void
    {
        // FAKETIME 从 2026-10-10 17:00 起算，之后按真实流逝继续走，不是永远停在 10 日。
        $today = (new \DateTimeImmutable('now'))->format('Y-m');

        self::assertSame('2026-10', $today);
    }
}
