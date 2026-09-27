<?php

declare(strict_types=1);

/**
 * timezone 是游戏时区，签到的「今天」、年月、可签最大档，以及活动窗口都用它。
 * servers 按 server_id 给出角色所在服务器时区。没有这个服时回退到游戏时区。
 * s1、s2 对应 config/accounts.php 里的服务器。
 *
 * @return array{timezone: string, servers: array<string, string>}
 */
return [
    'timezone' => 'Etc/GMT+5',
    'servers' => [
        's1' => 'Asia/Shanghai',
        's2' => 'Etc/GMT+5',
    ],
];
