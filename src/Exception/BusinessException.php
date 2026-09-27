<?php

declare(strict_types=1);

namespace Checkin\Exception;

use RuntimeException;

/**
 * 预期内的业务拒绝。错误处理器读 businessCode 和 httpStatus 写成 JSON。
 * 字符串码不能传给 RuntimeException：它的 code 参数必须是整数，所以这里只把文案交给父类。
 */
final class BusinessException extends RuntimeException
{
    public function __construct(
        private readonly string $businessCode,
        string $message,
        private readonly int $httpStatus = 400,
    ) {
        parent::__construct($message);
    }

    /** 写入响应体 code 字段的字符串，例如 UNAUTHORIZED。 */
    public function getBusinessCode(): string
    {
        return $this->businessCode;
    }

    /** 响应的 HTTP 状态。参数错误默认 400；401、404、409 要在抛出时写上。 */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
