<?php

declare(strict_types=1);

namespace Tests\Service;

use Checkin\Config\GameClock;
use Checkin\Database\DoctrineFactory;
use Checkin\Gift\HttpGiftClient;
use Checkin\Service\LoginService;
use Checkin\Service\RechargeService;
use PHPUnit\Framework\TestCase;

final class RechargeRecordTest extends TestCase
{
    public function testRecordIncreasesMonthTotalByTheAmount(): void
    {
        $em = DoctrineFactory::createEntityManager();
        $login = new LoginService($em);
        $recharge = new RechargeService($em, new HttpGiftClient(''), new GameClock());

        $role = $login->login('token-player-1001', 's3', 'r300');
        $roleId = $role['user_role_primary_id'];

        $before = $recharge->getStatus($roleId, 2)['totalRechargeGoods'];
        $after = $recharge->record($roleId, 2, 100);

        self::assertSame($before + 100, $after['totalRechargeGoods']);
    }
}
