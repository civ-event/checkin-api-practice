<?php

declare(strict_types=1);

namespace Checkin\Controllers;

use Checkin\Common\Lock;
use Doctrine\ORM\EntityManagerInterface;
use Memcached;
use Symfony\Component\HttpFoundation\Response;

/**
 * 内核探活。这些接口不走登录。
 * 返回 Response 的原样输出；返回数组的由 JsonResultHandler 包成 JSON。
 */
class SlimHealthController
{
    /** 正文是纯文本 slimapp ok */
    public function healthAction(): Response
    {
        return $this->text('slimapp ok');
    }

    /** 确认 services.yml 里的 Memcached 客户端能读到 stats */
    public function memcachedAction(Memcached $memcached): Response
    {
        $stats = $memcached->getStats();
        if (!is_array($stats) || $stats === []) {
            return $this->text('memcached fail', 500);
        }

        return $this->text('memcached ok');
    }

    /** 抢一把探测锁再释放 */
    public function lockAction(Lock $lock): Response
    {
        $lock->memcachedLock(static fn(): null => null, 'health:lock', 5, 0.0);

        return $this->text('lock ok');
    }

    /** SELECT 1，确认 Doctrine 连上 MySQL */
    public function dbAction(EntityManagerInterface $em): Response
    {
        $value = $em->getConnection()->fetchOne('SELECT 1');
        if ((string) $value !== '1') {
            return $this->text('db fail', 500);
        }

        return $this->text('db ok');
    }

    /** 数组返回值会被 JsonResultHandler 编成 JSON */
    public function jsonAction(): array
    {
        return ['ok' => true];
    }

    private function text(string $body, int $status = 200): Response
    {
        return new Response($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
