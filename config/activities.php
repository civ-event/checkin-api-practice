<?php

declare(strict_types=1);

return [
    // 签到活动。JWT 里的 activity_id 是 1
    1 => [
        'name' => '月度签到',
        'starts_at' => '2026-09-01 00:00:00',
        'ends_at' => '2026-09-30 23:59:59',
    ],
    // 累充活动。请求里的 activity_id 必须是 2
    2 => [
        'name' => '月度累充',
        'starts_at' => '2026-09-01 00:00:00',
        'ends_at' => '2026-09-30 23:59:59',
    ],
];
