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
 * 把异常收成前端错误体 {code, exception:{type, message}}。
 * 必须返回 JsonResponse，HTTP 状态才会按业务码走；只返回数组的话，内核仍把状态留在 500。
 * 成功响应不走这里。4xx 带具体说明，5xx 用通用文案，响应里不放 file/line。
 */
final class SlimJsonErrorHandler
{
    public function __invoke(\Exception $exception, Request $request, int $code): JsonResponse
    {
        $status = 500;
        $businessCode = ErrorCode::INTERNAL_ERROR;
        $message = 'Internal server error, please try again later.';

        if ($exception instanceof BusinessException) {
            $status = $exception->getHttpStatus();
            $businessCode = $exception->getBusinessCode();
            $message = $exception->getMessage();
        } elseif ($exception instanceof LockAcquireException) {
            $status = 409;
            $businessCode = ErrorCode::RESOURCE_BUSY;
            $message = 'The resource is busy, please retry.';
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $businessCode = $status >= 500 ? ErrorCode::INTERNAL_ERROR : (new \ReflectionClass($exception))->getShortName();
            $message = $status >= 500
                ? 'Internal server error, please try again later.'
                : ($exception->getMessage() !== '' ? $exception->getMessage() : 'http error');
        }

        $response = new JsonResponse([
            'code' => $businessCode,
            'exception' => [
                'type' => (new \ReflectionClass($exception))->getShortName(),
                'message' => $message,
            ],
        ], $status);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $response;
    }
}
