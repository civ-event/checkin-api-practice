<?php

declare(strict_types=1);

return [
    // 键是游戏角色 role_id，不是 activity_user_role 自增主键。角色甲先登录也不会把乙的流水错记到甲上。
    // send_time 是 Unix 秒。月份按角色所在服务器时区解释，不用墙上时间字符串。
    // 角色甲：本月没有支付
    'r100' => [],
    // 角色乙在 s2（Etc/GMT+5）。两笔 send_time 都落在该时区的 2026-09，合计 350。达到 100、300 两档，500 档未达到。
    'r200' => [
        ['order_id' => 'pay-2001', 'amount' => 100, 'send_time' => 1788274800],
        ['order_id' => 'pay-2002', 'amount' => 250, 'send_time' => 1789059600],
    ],
];
