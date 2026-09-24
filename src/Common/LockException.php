<?php

declare(strict_types=1);

namespace Checkin\Common;

/** Memcached 本身故障（连不上、服务端错误）。这不是「锁被别人拿着」，调用方不应当成可重试的业务冲突。 */
class LockException extends \RuntimeException
{
}
