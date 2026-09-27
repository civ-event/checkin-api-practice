<?php

declare(strict_types=1);

namespace Checkin\Gift;

/**
 * 向游戏服 POST 发奖。GIFT_API_URL 为空时不发请求，只把同一份正文写进日志。
 */
final class HttpGiftClient implements GiftGrantClientInterface
{
    public function __construct(private readonly string $url) {}

    public function grant(int $userRolePrimaryId, int $activityId, array $payload): void
    {
        $body = json_encode([
            'rewardType' => $payload['rewardType'],
            'serverId' => $payload['serverId'],
            'roleId' => $payload['roleId'],
            'playerId' => $payload['playerId'],
            'itemList' => $payload['itemList'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($this->url === '') {
            error_log(sprintf('[gift] mock role_pk=%d activity=%d body=%s', $userRolePrimaryId, $activityId, $body));

            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($this->url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }
        if ($response === false || $status < 200 || $status >= 300) {
            error_log(sprintf(
                '[gift] http failed role_pk=%d activity=%d status=%d body=%s',
                $userRolePrimaryId,
                $activityId,
                $status,
                $body,
            ));
            throw new \RuntimeException('gift http failed');
        }

        error_log(sprintf('[gift] sent role_pk=%d activity=%d status=%d body=%s', $userRolePrimaryId, $activityId, $status, $body));
    }
}
