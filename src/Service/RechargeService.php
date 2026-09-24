<?php

declare(strict_types=1);

namespace Checkin\Service;

use Checkin\Common\ErrorCode;
use Checkin\Exception\BusinessException;
use Checkin\Gift\GiftGrantClientInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Checkin\Config\ActivitySchedule;

/**
 * 月度累计充值。进度按角色主键 + 活动 2 + YYYYMM 一行。
 * 登录 JWT 里的 activity_id 固定是签到活动 1，这里不用它，避免和签到进度写到同一条。
 * 当月金额以 config/mock_payments.php 的订单合计为准，客户端上报的金额不累加；线上金额来自支付同步，不由客户端上报。
 */
final class RechargeService
{
    /** 与 config/recharge.php 的 activity_id 一致；签到活动是 1 */
    private const ACTIVITY_ID = 2;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GiftGrantClientInterface $giftClient,
        private readonly string $timezone,
    ) {}

    /** 服务器时区的 YYYYMM，例如 202609。换月后是另一行，进度自然重置。 */
    public function currentYearMonth(): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));

        return (int) $now->format('Ym');
    }

    /** 请求必须带 activity_id，只接受 2，并且当前时间在活动窗口内。错活动或不在窗口都是 10014。 */
    private function assertRechargeActivity(int $activityId): void
    {
        if ($activityId !== self::ACTIVITY_ID) {
            throw new BusinessException(ErrorCode::ACTIVITY_NOT_RUNNING, 'Activity is not the monthly recharge activity');
        }

        ActivitySchedule::assertRunning(self::ACTIVITY_ID, $this->timezone);
    }

    /** @return array{year_month: int, totalAmount: int, tiers: list<array{threshold: int, status: string, gift_id: int, gift_name: string}>} */
    public function getStatus(int $userRolePrimaryId, int $activityId): array
    {
        $this->assertRechargeActivity($activityId);
        $this->applyMockTotal($userRolePrimaryId);
        $yearMonth = $this->currentYearMonth();
        $row = $this->findOrCreate($userRolePrimaryId, $yearMonth);
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
                'status' => $status,
                'gift_id' => $tier['gift_id'],
                'gift_name' => $tier['gift_name'],
            ];
        }

        return [
            'year_month' => $yearMonth,
            'totalAmount' => $total,
            'tiers' => $tiers,
        ];
    }

    public function record(int $userRolePrimaryId, int $activityId, int $amount): array
    {
        if ($amount < 1) {
            throw new BusinessException(ErrorCode::INVALID_PARAM, 'amount must be a positive int');
        }

        $this->assertRechargeActivity($activityId);
        $this->applyMockTotal($userRolePrimaryId);

        return $this->getStatus($userRolePrimaryId, $activityId);
    }

    public function claim(int $userRolePrimaryId, int $activityId, int $threshold): array
    {
        $tier = $this->tiers()[$threshold] ?? null;
        if ($tier === null) {
            throw new BusinessException(ErrorCode::INVALID_RECHARGE_TIER, 'Invalid recharge tier');
        }

        $this->assertRechargeActivity($activityId);
        $this->applyMockTotal($userRolePrimaryId);
        $yearMonth = $this->currentYearMonth();
        $row = $this->findOrCreate($userRolePrimaryId, $yearMonth);
        $claimed = $this->claimedTiers($row['claimed_tiers']);

        // 先看金额再看是否已领：未达标是 10012，达标但领过是 10013
        if ((int) $row['total_amount'] < $threshold) {
            throw new BusinessException(ErrorCode::RECHARGE_NOT_REACHED, 'Recharge amount not reached');
        }
        if (in_array($threshold, $claimed, true)) {
            throw new BusinessException(ErrorCode::RECHARGE_ALREADY_CLAIMED, 'Recharge reward already claimed');
        }

        $claimed[] = $threshold;
        sort($claimed);
        $now = time();

        $this->em->getConnection()->update('monthly_recharge_user_data', [
            'claimed_tiers' => json_encode(array_values($claimed), JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ], ['id' => $row['id']]);

        // day 在充值里表示门槛金额，不是签到天数。先落库再发奖：发奖失败时库里已是已领
        $gift = [
            'day' => $threshold,
            'gift_id' => $tier['gift_id'],
            'gift_name' => $tier['gift_name'],
        ];
        $this->giftClient->grant($userRolePrimaryId, self::ACTIVITY_ID, $gift);

        return [
            'status' => 'success',
            'year_month' => $yearMonth,
            'threshold' => $threshold,
            'gift' => [
                'gift_id' => $tier['gift_id'],
                'gift_name' => $tier['gift_name'],
            ],
        ];
    }

    /** 用假支付流水覆盖当月 total_amount。同一角色同一月反复调用结果相同 */
    private function applyMockTotal(int $userRolePrimaryId): void
    {
        $yearMonth = $this->currentYearMonth();
        $total = $this->mockTotal($userRolePrimaryId, $yearMonth);
        $row = $this->findOrCreate($userRolePrimaryId, $yearMonth);
        if ((int) $row['total_amount'] === $total) {
            return;
        }

        $this->em->getConnection()->update('monthly_recharge_user_data', [
            'total_amount' => $total,
            'updated_at' => time(),
        ], ['id' => $row['id']]);
    }

    /** 只加总 paid_at 落在当前 YYYYMM 的订单 */
    private function mockTotal(int $userRolePrimaryId, int $yearMonth): int
    {
        /** @var array<int, list<array{order_id: string, amount: int, paid_at: string}>> $all */
        $all = require dirname(__DIR__, 2) . '/config/mock_payments.php';
        $orders = $all[$userRolePrimaryId] ?? [];
        $tz = new \DateTimeZone($this->timezone);
        $sum = 0;
        foreach ($orders as $order) {
            $paid = new \DateTimeImmutable($order['paid_at'], $tz);
            if ((int) $paid->format('Ym') !== $yearMonth) {
                continue;
            }
            $sum += (int) $order['amount'];
        }

        return $sum;
    }

    /** @return array{id: int|string, total_amount: int|string, claimed_tiers: string} */
    private function findOrCreate(int $userRolePrimaryId, int $yearMonth): array
    {
        $conn = $this->em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT id, total_amount, claimed_tiers FROM monthly_recharge_user_data WHERE user_role_primary_id = ? AND activity_id = ? AND `year_month` = ?',
            [$userRolePrimaryId, self::ACTIVITY_ID, $yearMonth],
        );
        if ($row !== false) {
            return $row;
        }

        $now = time();
        try {
            // DBAL 的 insert() 不给列名加引号。year_month 是 MySQL 关键字，
            // 键必须自带反引号，否则 1064，接口包成 50000。SELECT 里同理。
            $conn->insert('monthly_recharge_user_data', [
                'user_role_primary_id' => $userRolePrimaryId,
                'activity_id' => self::ACTIVITY_ID,
                '`year_month`' => $yearMonth,
                'total_amount' => 0,
                'claimed_tiers' => '[]',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            $row = $conn->fetchAssociative(
                'SELECT id, total_amount, claimed_tiers FROM monthly_recharge_user_data WHERE user_role_primary_id = ? AND activity_id = ? AND `year_month` = ?',
                [$userRolePrimaryId, self::ACTIVITY_ID, $yearMonth],
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
