<?php

declare(strict_types=1);

namespace Checkin\Http;

use Checkin\Exception\BusinessException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * SlimApp 的 error handler。返回 JsonResponse，HTTP 状态才会按业务码走。
 * 如果只返回数组，内核仍把状态留在 500。
 * BusinessException 用自己的 code 和 HTTP 状态；其它异常用 50000。
 */
final class SlimJsonErrorHandler
{
    public function __invoke(\Exception $exception, Request $request, int $code): JsonResponse
    {
        $status = 500;
        $businessCode = 50000;
        $message = 'internal error';
        $data = null;

        if ($exception instanceof BusinessException) {
            // 例如已领过是 code 10008、HTTP 400；没带 token 是 10004、HTTP 401
            $status = $exception->getHttpStatus();
            $businessCode = $exception->getBusinessCode();
            $message = $exception->getMessage();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $businessCode = $status * 100;
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'http error';
        } elseif ((getenv('APP_DEBUG') ?: '0') === '1') {
            $message = $exception->getMessage();
        }

        // 业务拒绝不附带文件行号。未接住的异常在调试模式下带上位置
        if ((getenv('APP_DEBUG') ?: '0') === '1' && !$exception instanceof BusinessException) {
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
