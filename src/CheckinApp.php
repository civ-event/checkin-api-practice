<?php

declare(strict_types=1);

namespace Checkin;

use Checkin\Auth\JwtService;
use Checkin\Common\Lock;
use Checkin\Config\DailyCheckInConfig;
use Checkin\Config\GameClock;
use Checkin\Database\DoctrineFactory;
use Checkin\Entities\DailyCheckInUserData;
use Checkin\Entities\Repositories\DailyCheckInUserDataRepository;
use Checkin\Gift\HttpGiftClient;
use Checkin\Service\CheckInService;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\SlimApp\SlimApp;
use Checkin\Service\RechargeService;
use Checkin\Service\LoginService;

/**
 * 公司 SlimApp 的练习入口。
 * public/index.php 和 public/front.php 都加载这个类。
 * index.php 处理 /api-front、/api-auth 这些完整路径；front.php 处理脚本名后面的短路径。
 * 控制器要的对象必须在这里 addControllerInjectedArg，内核只按具体类注入，接口类型对不上。
 */
class CheckinApp extends SlimApp
{
    public function getHttpKernel(): MicroKernel
    {
        // 同一个请求里内核只建一次
        if (!$this->microKernel instanceof MicroKernel) {
            $container = $this->container;
            assert($container !== null, 'SlimApp not initialized');
            // httpConfig 来自 services.yml 的 app.properties.http（路由、view handler）
            $kernel = new MicroKernel($this->httpConfig ?? [], $this->isDebugMode);
            // 注入应用自身，控制器可以用 CheckinApp $app 取出容器里的服务
            $kernel->addControllerInjectedArg($this);
            // 公司风格的 Memcached 锁，控制器类型写成 Lock 即可拿到
            $memcached = $this->getService('memcached', \Memcached::class);
            $kernel->addControllerInjectedArg(new Lock($memcached));
            $kernel->addControllerInjectedArg($memcached);
            // 本请求共用这一个 EntityManager，避免状态查询再连一次库
            $em = DoctrineFactory::createEntityManager();
            $repo = $em->getRepository(DailyCheckInUserData::class);
            assert($repo instanceof DailyCheckInUserDataRepository);
            $kernel->addControllerInjectedArg($em);
            // 签到用游戏时区。时区是字符串，不能靠类型自动装配。
            $gameClock = new GameClock();
            $giftClient = new HttpGiftClient(getenv('GIFT_API_URL') ?: '');
            $kernel->addControllerInjectedArg(new CheckInService(
                $repo,
                DailyCheckInConfig::load(),
                $gameClock->gameTimezone()->getName(),
                $em,
                $giftClient,
            ));

            // 累充活动窗口用游戏时区，年月和订单月份用角色所在服务器时区。
            $kernel->addControllerInjectedArg(new RechargeService(
                $em,
                $giftClient,
                $gameClock,
            ));
            // 新入口暂时仍校验旧登录接口签发的 JWT，还没换成公司的 RequestSender
            $kernel->addControllerInjectedArg(new JwtService());
            $kernel->addExtraParameters($container->getParameterBag()->all());
            // 假账号登录。JWT 里的 role_id 是角色表主键，activity_id 仍写成 1，业务接口不使用它。
            $kernel->addControllerInjectedArg(new LoginService($em));
            $this->microKernel = $kernel;
        }

        return $this->microKernel;
    }
}
