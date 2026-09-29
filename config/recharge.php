<?php

declare(strict_types=1);

return [
    // 这里只放档位礼物。正在进行的活动 id 来自请求和 config/activities.php。
    'activity_id' => 2,
    'tiers' => [
        100 => ['threshold' => 100, 'gift_id' => 2001, 'gift_name' => '充值礼包*100'],
        300 => ['threshold' => 300, 'gift_id' => 2002, 'gift_name' => '充值礼包*300'],
        500 => ['threshold' => 500, 'gift_id' => 2003, 'gift_name' => '充值礼包*500'],
        800 => ['threshold' => 800, 'gift_id' => 2004, 'gift_name' => '充值礼包*800'],
        1000 => ['threshold' => 1000, 'gift_id' => 2005, 'gift_name' => '充值礼包*1000'],
        2000 => ['threshold' => 2000, 'gift_id' => 2006, 'gift_name' => '充值礼包*2000'],
    ],
];
