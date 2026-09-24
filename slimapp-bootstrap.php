<?php

declare(strict_types=1);

use Checkin\CheckinApp;
use Checkin\CheckinConfiguration;

require_once __DIR__ . '/vendor/autoload.php';

// 单例应用。init 读取 config/config.yml 和 config/services.yml，编译结果放在第三个参数目录。
// 改了 services.yml 后要删掉 var/cache/config，否则还会用上一次编译好的容器。
$app = CheckinApp::app();
$app->init(__DIR__ . '/config', new CheckinConfiguration(), __DIR__ . '/var/cache/config');

return $app;
