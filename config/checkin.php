<?php // PHP 起始

declare(strict_types=1); // 严格类型

/**
 * 签到活动模板（学习用，代替 YAML 配置文件）。
 * DailyCheckInConfig::load() 会 require 本文件。
 *
 * @return array{
 *   max_day: int,
 *   days: array<int, array{day: int, gift_id: int, gift_name: string}>
 * }
 */
return [
    'makeup_check_in_limit' => 1, // 每月补签次数。0 表示今天签过后不能再签
    'max_day' => 7, // 活动最长签到天数（文档用；逻辑主要看 days 的 key）
    'days' => [ // key = 签到天序号；value = 该天礼物
        1 => ['day' => 1, 'gift_id' => 1001, 'gift_name' => '金币*100'], // 第 1 天
        2 => ['day' => 2, 'gift_id' => 1002, 'gift_name' => '金币*200'], // 第 2 天
        3 => ['day' => 3, 'gift_id' => 1003, 'gift_name' => '钻石*10'], // 第 3 天
        4 => ['day' => 4, 'gift_id' => 1004, 'gift_name' => '金币*300'], // 第 4 天
        5 => ['day' => 5, 'gift_id' => 1005, 'gift_name' => '钻石*20'], // 第 5 天
        6 => ['day' => 6, 'gift_id' => 1006, 'gift_name' => '金币*500'], // 第 6 天
        7 => ['day' => 7, 'gift_id' => 1007, 'gift_name' => '大奖宝箱'], // 第 7 天大奖
    ],
];
