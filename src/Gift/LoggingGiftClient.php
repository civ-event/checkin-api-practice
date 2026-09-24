<?php

declare(strict_types=1);

namespace Checkin\Gift;

/** 练习实现：只写 error_log。出现 [gift] sent 表示发奖函数被调用，不表示游戏已到账。 */
final class LoggingGiftClient implements GiftGrantClientInterface
{
    public function grant(int $roleId, int $activityId, array $gift): void
    {
        error_log(sprintf(
            '[gift] sent role=%d activity=%d day=%d gift_id=%d gift_name=%s',
            $roleId,
            $activityId,
            $gift['day'],
            $gift['gift_id'],
            $gift['gift_name'],
        ));
    }
}
