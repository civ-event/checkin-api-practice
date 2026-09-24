<?php

declare(strict_types=1);

// 和 index.php 同一套 SlimApp。/front.php/check-in/status 这类地址仍可用。。
$app = require dirname(__DIR__) . '/slimapp-bootstrap.php';
$app->getHttpKernel()->run();
