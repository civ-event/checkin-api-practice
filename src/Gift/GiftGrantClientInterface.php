<?php

declare(strict_types=1);

namespace Checkin\Gift;

interface GiftGrantClientInterface
{
    /**
     * 发出领奖请求。调用返回即表示请求已提交，不表示游戏内已到账。
     *
     * @param array{day: int, gift_id: int, gift_name: string} $gift
     */
    public function grant(int $roleId, int $activityId, array $gift): void;
}
