<?php

declare(strict_types=1);

/**
 * timezone 是游戏时区，签到的「今天」、年月、可签最大档，以及活动窗口都用它。
 * servers 按 server_id 给出角色所在服务器时区。没有这个服时回退到游戏时区。
 * s1～s4 对应 config/accounts.php。甲、丁的 s1、s4 是 Etc/GMT+5；乙、丙的 s2、s3 是 Asia/Shanghai。
 *
 * @return array{timezone: string, servers: array<string, string>}
 */
return [
    'timezone' => 'Etc/GMT+5',
    'servers' => [
        's1' => 'Etc/GMT+5',
        's2' => 'Asia/Shanghai',
        's3' => 'Asia/Shanghai',
        's4' => 'Etc/GMT+5',
    ],
];
