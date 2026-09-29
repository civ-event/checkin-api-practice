<?php

declare(strict_types=1);

return [
    // 键是游戏角色 role_id，不是 activity_user_role 自增主键。角色甲先登录也不会把乙的流水错记到甲上。
    // send_time 是 Unix 秒。月份按角色所在服务器时区解释，不用墙上时间字符串。
    // 角色甲在 s1（Etc/GMT+5）。三笔 send_time 都落在 2026-09，合计 900。订单号互不相同。10 月合计不含这三笔。
    'r100' => [
        ['order_id' => 'pay-1001', 'amount' => 100, 'send_time' => 1788274800],
        ['order_id' => 'pay-1002', 'amount' => 300, 'send_time' => 1789059600],
        ['order_id' => 'pay-1003', 'amount' => 500, 'send_time' => 1789146000],
    ],
    // 角色乙在 s2（Asia/Shanghai）。两笔 send_time 都落在 2026-09，合计 600。这 600 只计入 9 月。
    'r200' => [
        ['order_id' => 'pay-2001', 'amount' => 100, 'send_time' => 1788274800],
        ['order_id' => 'pay-2002', 'amount' => 500, 'send_time' => 1789059600],
    ],
];
