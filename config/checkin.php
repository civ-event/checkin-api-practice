<?php

declare(strict_types=1);

/**
 * 月度签到档位。天数和补签次数对齐线上 monthlyCheckIn/mislen-2025-12.yml：31 天，每月 3 次。
 * DailyCheckInConfig::load() 会 require 本文件。逻辑看 days 的 key，不看 max_day。
 *
 * @return array{
 *   makeup_check_in_limit: int,
 *   max_day: int,
 *   days: array<int, array{day: int, gift_id: int, gift_name: string}>
 * }
 */
$gifts = [
    1 => '钻*200*4',
    2 => '糖果*10*Item_GetCE_10',
    3 => '金戒指*2*gift1',
    4 => '仙灵瓶*3*Item_Token_Gacha_Universal',
    5 => '鲜花项链*5*gift3',
    6 => '相遇之石碎片*1*Item_Piece_Hero_Universal',
    7 => '低级魔药*2*Item_Hero_Attribute_Increase_1',
];

$days = [];
for ($day = 1; $day <= 31; $day++) {
    $days[$day] = [
        'day' => $day,
        'gift_id' => 1000 + $day,
        'gift_name' => $gifts[(($day - 1) % 7) + 1],
    ];
}

return [
    'makeup_check_in_limit' => 3,
    'max_day' => 31,
    'days' => $days,
];
