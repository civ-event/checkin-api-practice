<?php

declare(strict_types=1);

namespace Checkin\Common;

/** 等待时间内没抢到锁。锁被其他请求正常持有，调用方可以稍后重试。 */
class LockAcquireException extends \RuntimeException
{
}
