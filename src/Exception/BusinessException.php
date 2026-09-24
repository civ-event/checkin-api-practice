<?php // PHP 起始

declare(strict_types=1); // 严格类型

namespace Checkin\Exception; // 异常命名空间

use RuntimeException; // PHP 标准运行时异常基类

/**
 * 业务异常：携带业务 code + HTTP 状态码，
 * 由 JsonErrorHandler 转成统一 JSON，不冒成 500。
 */
final class BusinessException extends RuntimeException
{
    public function __construct(
        private readonly int $businessCode, // 业务错误码（如 10002）
        string $message, // 给人看的错误文案
        private readonly int $httpStatus = 400, // 对应 HTTP 状态，默认 400
    ) {
        parent::__construct($message, $businessCode); // 传给父类：message + code
    }

    /** 取出业务错误码，写入 JSON 的 code 字段 */
    public function getBusinessCode(): int
    {
        return $this->businessCode;
    }

    /** 取出建议的 HTTP 状态码（如锁冲突用 429） */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
