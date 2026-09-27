<?php

declare(strict_types=1);

namespace Checkin\Config;

/**
 * 从 config/game.php 读取时区。
 * 签到用游戏时区。累充月份用角色 server_id 对应的服务器时区。
 */
final class GameClock
{
    public function gameTimezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->config()['timezone']);
    }

    /** 有这个服就用它的时区，没有就回退到游戏时区。 */
    public function serverTimezone(string $serverId): \DateTimeZone
    {
        $name = $this->config()['servers'][$serverId] ?? '';
        if (!is_string($name) || $name === '') {
            return $this->gameTimezone();
        }

        return new \DateTimeZone($name);
    }

    /** @return array{timezone: string, servers: array<string, string>} */
    private function config(): array
    {
        /** @var array{timezone: string, servers: array<string, string>} $config */
        $config = require dirname(__DIR__, 2) . '/config/game.php';

        return $config;
    }
}
