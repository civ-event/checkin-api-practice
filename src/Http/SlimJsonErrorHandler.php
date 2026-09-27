<?php

declare(strict_types=1);

namespace Checkin\Http;

use Checkin\Common\ErrorCode;
use Checkin\Common\LockAcquireException;
use Checkin\Exception\BusinessException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * 把异常收成 JSON。必须返回 JsonResponse，HTTP 状态才会按业务码走；
 * 只返回数组的话，内核仍把状态留在 500。
 * 成功响应不走这里，仍由 JsonResultHandler 写成 code 0。
 */
final class SlimJsonErrorHandler
{
    public function __invoke(\Exception $exception, Request $request, int $code): JsonResponse
    {
        // 没被下面分支接住的异常，统一当成内部错误。
        $status = 500;
        $businessCode = ErrorCode::INTERNAL_ERROR;
        $message = 'Internal server error, please try again later.';
        $data = null;

        if ($exception instanceof BusinessException) {
            // 业务拒绝：码和 HTTP 状态都由抛出的地方决定。
            $status = $exception->getHttpStatus();
            $businessCode = $exception->getBusinessCode();
            $message = $exception->getMessage();
        } elseif ($exception instanceof LockAcquireException) {
            // 锁被别人占着。请求本身合法，所以是 409，客户端可以重试。
            $status = 409;
            $businessCode = ErrorCode::RESOURCE_BUSY;
            $message = 'The resource is busy, please retry.';
        } elseif ($exception instanceof HttpExceptionInterface) {
            // 框架抛出的 HTTP 异常。状态用它自己的，业务码仍是内部错误字符串。
            $status = $exception->getStatusCode();
            $businessCode = ErrorCode::INTERNAL_ERROR;
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'http error';
        } elseif ((getenv('APP_DEBUG') ?: '0') === '1') {
            $message = $exception->getMessage();
        }

        // 业务拒绝和锁冲突不附带文件行号。其余异常在调试模式下带上位置。
        if ((getenv('APP_DEBUG') ?: '0') === '1'
            && !$exception instanceof BusinessException
            && !$exception instanceof LockAcquireException
        ) {
            $data = [
                'type' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $response = new JsonResponse([
            'code' => $businessCode,
            'message' => $message,
            'data' => $data,
        ], $status);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $response;
    }
}
