<?php

declare(strict_types=1);

namespace Checkin\Common;

use Memcached;

/**
 * 公司风格的 Memcached 锁，对齐 Lock::memcachedLock。
 * 抢锁用 add（key 不存在才写入）。释放用 CAS，避免锁过期后被别人拿走、自己又把它删掉。
 * * 新入口的打卡、领奖、充值都用这把锁：add 抢锁，CAS 释放。
 */
final class Lock
{
    /** 锁默认存活 30 秒，要盖住一次签到或发奖 */
    private const int DEFAULT_TTL = 30;

    /** 默认最多等 5 秒。探测接口会传 0，抢不到立刻失败 */
    private const float DEFAULT_WAIT_TIMEOUT = 5.0;

    /** 没抢到时隔 50 毫秒再试 */
    private const int RETRY_INTERVAL_US = 50_000;

    /** 释放时不直接 delete，改写成 1 秒后过期的空值，避免和下一任持有者抢同一个 CAS */
    private const int TOMBSTONE_TTL = 1;

    public function __construct(
        private readonly Memcached $memcached,
    ) {}

    /**
     * 抢到锁后执行回调，无论成功或抛错都尝试释放。
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function memcachedLock(
        callable $callback,
        string $lockKey,
        int $ttl = self::DEFAULT_TTL,
        float $waitTimeout = self::DEFAULT_WAIT_TIMEOUT,
    ): mixed {
        $storageKey = $this->buildStorageKey($lockKey, $ttl, $waitTimeout);
        $token = $this->acquire($storageKey, $ttl, $waitTimeout);

        try {
            return $callback();
        } finally {
            $this->release($storageKey, $token);
        }
    }

    /** 自旋直到 add 成功，或超过等待时间。连接故障抛 LockException，别人持有抛 LockAcquireException */
    private function acquire(string $storageKey, int $ttl, float $waitTimeout): string
    {
        $token = bin2hex(random_bytes(16));
        $deadline = microtime(true) + $waitTimeout;

        do {
            // add 只有 key 不存在时才成功，用来做互斥
            if ($this->memcached->add($storageKey, $token, $ttl)) {
                return $token;
            }

            $resultCode = $this->memcached->getResultCode();
            $heldByOthers = $resultCode === Memcached::RES_NOTSTORED
                || $resultCode === Memcached::RES_DATA_EXISTS;

            if (!$heldByOthers) {
                throw new LockException(sprintf(
                    'Memcached error while acquiring lock: %s (code %d)',
                    $this->memcached->getResultMessage(),
                    $resultCode
                ));
            }

            // 上一任已经释放，key 里是 1 秒空占位。直接接手，避免紧接着的补签或领奖被这 1 秒挡住
            if ($this->takeTombstone($storageKey, $token, $ttl)) {
                return $token;
            }

            usleep(self::RETRY_INTERVAL_US);
        } while (microtime(true) < $deadline);

        throw new LockAcquireException('Failed to acquire lock within ' . $waitTimeout . 's');
    }

    /** 只有当前值仍是自己的 token 时才用 CAS 盖掉。值已经变了说明锁过期并被别人拿走，不能删 */
    private function release(string $storageKey, string $token): void
    {
        $result = $this->memcached->get($storageKey, null, Memcached::GET_EXTENDED);
        if (!is_array($result) || !isset($result['value'], $result['cas'])) {
            return;
        }
        if ($result['value'] !== $token) {
            return;
        }

        $this->memcached->cas((float) $result['cas'], $storageKey, '', self::TOMBSTONE_TTL);
    }

    /** 空占位说明锁已释放。CAS 换成自己的 token，避免和仍在持有的人互相覆盖 */
    private function takeTombstone(string $storageKey, string $token, int $ttl): bool
    {
        $result = $this->memcached->get($storageKey, null, Memcached::GET_EXTENDED);
        if (!is_array($result) || !isset($result['value'], $result['cas']) || $result['value'] !== '') {
            return false;
        }

        return $this->memcached->cas((float) $result['cas'], $storageKey, $token, $ttl);
    }

    /** 业务 key 先做校验，再哈希成固定长度，避免空格或过长 key 让 Memcached 拒绝 */
    private function buildStorageKey(string $lockKey, int $ttl, float $waitTimeout): string
    {
        if ($lockKey === '') {
            throw new \InvalidArgumentException('Lock key must not be empty');
        }
        if ($ttl < 1) {
            // ttl 0 在 Memcached 里表示永不过期，进程崩溃后会变成死锁
            throw new \InvalidArgumentException('Lock TTL must be at least 1 second');
        }
        if (!is_finite($waitTimeout) || $waitTimeout < 0) {
            throw new \InvalidArgumentException('Lock wait timeout must be a finite, non-negative number');
        }

        return 'lock:' . hash('xxh128', $lockKey);
    }
}
