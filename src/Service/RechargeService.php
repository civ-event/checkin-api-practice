<?php

declare(strict_types=1);

namespace Checkin\Service;

use Checkin\Common\ErrorCode;
use Checkin\Config\GameClock;
use Checkin\Exception\BusinessException;
use Checkin\Gift\GiftGrantClientInterface;
use Checkin\Gift\GiftPayload;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Checkin\Config\RunningActivity;

/**
 * 月度累计充值。进度按角色主键 + 请求里的活动 id + YYYYMM 一行。
 * 活动 id 来自请求，类型必须是 monthly_cumulative_recharge，对不上是 ACTIVITY_NOT_FOUND。
 * 活动窗口用游戏时区。年月和订单月份用角色所在服务器时区。
 * 登录 JWT 里的 activity_id 固定是签到活动，这里不用它。
 * 当月金额是 recharge_payment_orders 里落在当月的订单合计。mock_payments.php 只导入一次作为初始订单。
 * 领奖先和唯一领取记录一起落库，再请求游戏服。发奖失败不回滚。
 */
final class RechargeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GiftGrantClientInterface $giftClient,
        private readonly GameClock $clock,
    ) {}

    /** 角色所在服务器时区下的 YYYYMM。换月后是另一行，进度自然重置。 */
    public function currentYearMonth(int $userRolePrimaryId): int
    {
        $now = new \DateTimeImmutable('now', $this->serverTimezoneForRole($userRolePrimaryId));

        return (int) $now->format('Ym');
    }

    /** 请求里的活动必须是正在进行的 monthly_cumulative_recharge，否则 ACTIVITY_NOT_FOUND。窗口按游戏时区。 */
    private function assertRechargeActivity(int $activityId): array
    {
        return RunningActivity::find(
            'monthly_cumulative_recharge',
            $activityId,
            $this->clock->gameTimezone()->getName(),
        );
    }

    /** @return array{yearMonth: int, totalRechargeGoods: int, roundEndAt: int, tiers: list<array{threshold: int, reached: bool, status: string}>} */
    public function getStatus(int $userRolePrimaryId, int $activityId): array
    {
        $this->assertRechargeActivity($activityId);
        $row = $this->syncMonthTotal($userRolePrimaryId, $activityId);
        $yearMonth = $this->currentYearMonth($userRolePrimaryId);
        $claimed = $this->claimedTiers($row['claimed_tiers']);
        $total = (int) $row['total_amount'];

        $tiers = [];
        // 档位不要求按顺序领：达到门槛即可领，已领的保持 claimed
        foreach ($this->tiers() as $tier) {
            $threshold = $tier['threshold'];
            if (in_array($threshold, $claimed, true)) {
                $status = 'claimed';
            } elseif ($total >= $threshold) {
                $status = 'claimable';
            } else {
                $status = 'locked';
            }
            $tiers[] = [
                'threshold' => $threshold,
                'reached' => $total >= $threshold,
                'status' => $status,
            ];
        }

        return [
            'yearMonth' => $yearMonth,
            'totalRechargeGoods' => $total,
            'roundEndAt' => $this->roundEndAt($userRolePrimaryId),
            'tiers' => $tiers,
        ];
    }

    public function record(int $userRolePrimaryId, int $activityId, int $amount): array
    {
        if ($amount < 1) {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, 'amount must be a positive int');
        }

        $this->assertRechargeActivity($activityId);
        $this->insertClientOrder($userRolePrimaryId, $amount);

        return $this->getStatus($userRolePrimaryId, $activityId);
    }

    public function claim(int $userRolePrimaryId, int $activityId, int $threshold): array
    {
        $tier = $this->tiers()[$threshold] ?? null;
        if ($tier === null) {
            throw new BusinessException(ErrorCode::INVALID_RECHARGE_TIER, 'Invalid recharge tier');
        }

        $this->assertRechargeActivity($activityId);
        $row = $this->syncMonthTotal($userRolePrimaryId, $activityId);
        $yearMonth = $this->currentYearMonth($userRolePrimaryId);
        $claimed = $this->claimedTiers($row['claimed_tiers']);

        // 先看金额再看是否已领：没达到是 RECHARGE_NOT_REACHED，达到但领过是 REWARD_ALREADY_CLAIMED。
        if ((int) $row['total_amount'] < $threshold) {
            throw new BusinessException(ErrorCode::RECHARGE_NOT_REACHED, 'Recharge amount not reached');
        }
        if (in_array($threshold, $claimed, true)) {
            throw new BusinessException(ErrorCode::REWARD_ALREADY_CLAIMED, 'Recharge reward already claimed');
        }

        $claimed[] = $threshold;
        sort($claimed);
        $now = time();
        $rewardId = 'tier_' . $threshold;
        $gift = [
            'day' => $threshold,
            'gift_id' => $tier['gift_id'],
            'gift_name' => $tier['gift_name'],
        ];

        // 已领档位和唯一领取记录放进同一个事务。唯一索引冲突时两边都回滚。
        try {
            $this->em->getConnection()->transactional(function () use ($row, $claimed, $now, $userRolePrimaryId, $activityId, $yearMonth, $rewardId): void {
                $this->em->getConnection()->update('monthly_recharge_user_data', [
                    'claimed_tiers' => json_encode(array_values($claimed), JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ], ['id' => $row['id']]);
                $this->em->getConnection()->insert('monthly_cumulative_recharge_user_data_unique_records', [
                    'user_role_primary_id' => $userRolePrimaryId,
                    'activity_id' => $activityId,
                    'year_month_num' => $yearMonth,
                    'reward_id' => $rewardId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(ErrorCode::RESOURCE_BUSY, 'The resource is busy, please retry.', 409);
        }

        // 事务已提交。发奖失败不回滚。rewardType 与线上累充一致。
        $this->giftClient->grant(
            $userRolePrimaryId,
            $activityId,
            GiftPayload::build($this->em, $userRolePrimaryId, 'recharge', $gift),
        );

        return [
            'status' => 'success',
            'threshold' => $threshold,
            'claimedTier' => $threshold,
            'yearMonth' => $yearMonth,
            'claimedTiers' => array_map(static fn (int $tier): string => 'tier_' . $tier, $claimed),
        ];
    }

    /**
     * 初始订单导入一次，再按订单表重算当月 total_amount。
     *
     * @return array{id: int|string, total_amount: int|string, claimed_tiers: string}
     */
    private function syncMonthTotal(int $userRolePrimaryId, int $activityId): array
    {
        $role = $this->gameRole($userRolePrimaryId);
        $this->ensureSeedOrders($role['role_id']);
        $yearMonth = $this->currentYearMonth($userRolePrimaryId);
        $total = $this->orderTotal($role['role_id'], $role['server_id'], $yearMonth);
        $row = $this->findOrCreate($userRolePrimaryId, $activityId, $yearMonth);
        if ((int) $row['total_amount'] === $total) {
            return $row;
        }

        $this->em->getConnection()->update('monthly_recharge_user_data', [
            'total_amount' => $total,
            'updated_at' => time(),
        ], ['id' => $row['id']]);
        $row['total_amount'] = $total;

        return $row;
    }

    /** 记一笔客户端充值。支付时间用当前时间，所以落在角色服务器时区的当月。 */
    private function insertClientOrder(int $userRolePrimaryId, int $amount): void
    {
        $role = $this->gameRole($userRolePrimaryId);
        $now = time();
        $this->em->getConnection()->insert('recharge_payment_orders', [
            'role_id' => $role['role_id'],
            'order_id' => 'client-' . $now . '-' . bin2hex(random_bytes(3)),
            'amount' => $amount,
            'send_time' => $now,
            'created_at' => $now,
        ]);
    }

    /** mock_payments.php 只作为初始订单。同一角色的同一个订单号已经存在时不再插入。 */
    private function ensureSeedOrders(string $roleId): void
    {
        /** @var array<string, list<array{order_id: string, amount: int, send_time: int}>> $all */
        $all = require dirname(__DIR__, 2) . '/config/mock_payments.php';
        $orders = $all[$roleId] ?? [];
        $now = time();
        foreach ($orders as $order) {
            $this->em->getConnection()->executeStatement(
                'INSERT IGNORE INTO recharge_payment_orders (role_id, order_id, amount, send_time, created_at) VALUES (?, ?, ?, ?, ?)',
                [$roleId, $order['order_id'], $order['amount'], $order['send_time'], $now],
            );
        }
    }

    /** 同一角色、同一个订单号只加一次。重复同步到的订单不会把金额算两遍。 */
    private function orderTotal(string $roleId, string $serverId, int $yearMonth): int
    {
        /** @var list<array{id: int|string, order_id: string, amount: int|string, send_time: int|string}> $orders */
        $orders = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, order_id, amount, send_time FROM recharge_payment_orders WHERE role_id = ? ORDER BY id',
            [$roleId],
        );
        $serverTz = $this->clock->serverTimezone($serverId);
        $seen = [];
        $sum = 0;
        foreach ($orders as $order) {
            if (isset($seen[$order['order_id']])) {
                continue;
            }
            $seen[$order['order_id']] = true;
            $send = (new \DateTimeImmutable('@' . $order['send_time']))->setTimezone($serverTz);
            if ((int) $send->format('Ym') !== $yearMonth) {
                continue;
            }
            $sum += (int) $order['amount'];
        }

        return $sum;
    }

    /** @return array{role_id: string, server_id: string} */
    private function gameRole(int $userRolePrimaryId): array
    {
        $role = $this->em->getConnection()->fetchAssociative(
            'SELECT role_id, server_id FROM activity_user_role WHERE id = ?',
            [$userRolePrimaryId],
        );
        $roleId = is_array($role) && is_string($role['role_id'] ?? null) ? $role['role_id'] : '';
        $serverId = is_array($role) && is_string($role['server_id'] ?? null) ? $role['server_id'] : '';

        return ['role_id' => $roleId, 'server_id' => $serverId];
    }

    /** 下个自然月 1 号 00:00:00（角色所在服务器时区）的 Unix 秒。 */
    private function roundEndAt(int $userRolePrimaryId): int
    {
        $nextMonthStart = (new \DateTimeImmutable('now', $this->serverTimezoneForRole($userRolePrimaryId)))
            ->modify('first day of next month')
            ->setTime(0, 0, 0);

        return $nextMonthStart->getTimestamp();
    }

    private function serverTimezoneForRole(int $userRolePrimaryId): \DateTimeZone
    {
        $serverId = $this->em->getConnection()->fetchOne(
            'SELECT server_id FROM activity_user_role WHERE id = ?',
            [$userRolePrimaryId],
        );

        return $this->clock->serverTimezone(is_string($serverId) ? $serverId : '');
    }

    /** @return array{id: int|string, total_amount: int|string, claimed_tiers: string} */
    private function findOrCreate(int $userRolePrimaryId, int $activityId, int $yearMonth): array
    {
        $conn = $this->em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT id, total_amount, claimed_tiers FROM monthly_recharge_user_data WHERE user_role_primary_id = ? AND activity_id = ? AND `year_month` = ?',
            [$userRolePrimaryId, $activityId, $yearMonth],
        );
        if ($row !== false) {
            return $row;
        }

        $now = time();
        try {
            // DBAL 的 insert() 不给列名加引号。year_month 是 MySQL 关键字，
            // 键必须自带反引号，否则 SQL 1064，错误处理器会包成 INTERNAL_ERROR。SELECT 里同理。
            $conn->insert('monthly_recharge_user_data', [
                'user_role_primary_id' => $userRolePrimaryId,
                'activity_id' => $activityId,
                '`year_month`' => $yearMonth,
                'total_amount' => 0,
                'claimed_tiers' => '[]',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            $row = $conn->fetchAssociative(
                'SELECT id, total_amount, claimed_tiers FROM monthly_recharge_user_data WHERE user_role_primary_id = ? AND activity_id = ? AND `year_month` = ?',
                [$userRolePrimaryId, $activityId, $yearMonth],
            );
            if ($row === false) {
                throw new \RuntimeException('monthly recharge row missing after conflict');
            }

            return $row;
        }

        return [
            'id' => (int) $conn->lastInsertId(),
            'total_amount' => 0,
            'claimed_tiers' => '[]',
        ];
    }

    /** @return array<int, array{threshold: int, gift_id: int, gift_name: string}> */
    private function tiers(): array
    {
        /** @var array{tiers: array<int, array{threshold: int, gift_id: int, gift_name: string}>} $config */
        $config = require dirname(__DIR__, 2) . '/config/recharge.php';

        return $config['tiers'];
    }

    /** @return list<int> */
    private function claimedTiers(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        /** @var list<int> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return array_map('intval', $decoded);
    }
}
