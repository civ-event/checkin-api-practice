<?php

declare(strict_types=1);

namespace Tests\Service;

use Checkin\Common\ErrorCode;
use Checkin\Config\GameClock;
use Checkin\Database\DoctrineFactory;
use Checkin\Exception\BusinessException;
use Checkin\Gift\HttpGiftClient;
use Checkin\Service\LoginService;
use Checkin\Service\RechargeService;
use PHPUnit\Framework\TestCase;

final class RechargeClaimTest extends TestCase
{
    public function testClaimRejectsUnreachedTierThenClaimsOnce(): void
    {
        $em = DoctrineFactory::createEntityManager();
        $recharge = new RechargeService($em, new HttpGiftClient(''), new GameClock());
        $roleId = (new LoginService($em))->login('token-player-1001', 's3', 'r300')['user_role_primary_id'];
        $status = $recharge->getStatus($roleId, 2);

        $locked = null;
        $claimable = null;
        foreach ($status['tiers'] as $tier) {
            if ($locked === null && $tier['status'] === 'locked') {
                $locked = $tier['threshold'];
            }
            if ($claimable === null && $tier['status'] === 'claimable') {
                $claimable = $tier['threshold'];
            }
        }

        if ($locked !== null) {
            try {
                $recharge->claim($roleId, 2, $locked);
                self::fail('未达到的档位不能领');
            } catch (BusinessException $e) {
                self::assertSame(ErrorCode::RECHARGE_NOT_REACHED, $e->getBusinessCode());
            }
        }

        try {
            $recharge->claim($roleId, 2, 999);
            self::fail('配置里没有的档位不能领');
        } catch (BusinessException $e) {
            self::assertSame(ErrorCode::INVALID_RECHARGE_TIER, $e->getBusinessCode());
        }

        if ($claimable === null) {
            return;
        }

        $claimed = $recharge->claim($roleId, 2, $claimable);
        self::assertSame('success', $claimed['status']);
        self::assertSame($claimable, $claimed['threshold']);

        try {
            $recharge->claim($roleId, 2, $claimable);
            self::fail('同一档不能领两次');
        } catch (BusinessException $e) {
            self::assertSame(ErrorCode::REWARD_ALREADY_CLAIMED, $e->getBusinessCode());
        }
    }
}
