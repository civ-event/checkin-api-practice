<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$payload = [
    'status' => 'ok',
    'service' => getenv('APP_NAME') ?: 'checkin-api-practice',
    'time' => gmdate('c'),
];

http_response_code(200);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
