<?php

declare(strict_types=1);

namespace Checkin\Gift;

interface GiftGrantClientInterface
{
    /**
     * 请求游戏服发奖。调用返回只表示请求已发出。
     * 进度和唯一领取记录在调用前已经提交，这里失败不能再回滚。
     *
     * @param array{rewardType: string, serverId: string, roleId: string, playerId: string, itemList: string} $payload
     */
    public function grant(int $userRolePrimaryId, int $activityId, array $payload): void;
}
