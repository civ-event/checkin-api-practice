<?php

declare(strict_types=1);

namespace Tests\Config;

use Checkin\Config\GameClock;
use PHPUnit\Framework\TestCase;

final class GameClockTest extends TestCase
{
    public function testServerTimezones(): void
    {
        $clock = new GameClock();

        self::assertSame('Etc/GMT+5', $clock->gameTimezone()->getName());
        self::assertSame('Asia/Shanghai', $clock->serverTimezone('s2')->getName());
        self::assertSame('Etc/GMT+5', $clock->serverTimezone('missing')->getName());
    }
}
