<?php

declare(strict_types=1);

return [
    // 与 RechargeService::ACTIVITY_ID 保持一致。签到活动是 1，不要改成同一个
    'activity_id' => 2,
    'tiers' => [
        100 => ['threshold' => 100, 'gift_id' => 2001, 'gift_name' => '充值礼包*100'],
        300 => ['threshold' => 300, 'gift_id' => 2002, 'gift_name' => '充值礼包*300'],
        500 => ['threshold' => 500, 'gift_id' => 2003, 'gift_name' => '充值礼包*500'],
    ],
];
