<?php

declare(strict_types=1);

namespace Checkin;

use Checkin\Auth\JwtService;
use Checkin\Common\Lock;
use Checkin\Config\DailyCheckInConfig;
use Checkin\Database\DoctrineFactory;
use Checkin\Entities\DailyCheckInUserData;
use Checkin\Entities\Repositories\DailyCheckInUserDataRepository;
use Checkin\Gift\LoggingGiftClient;
use Checkin\Service\CheckInService;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\SlimApp\SlimApp;
use Checkin\Service\RechargeService;
use Checkin\Service\LoginService;

/**
 * 公司 SlimApp 的练习入口。
 * 旧的 Slim 4 仍走 public/index.php；这个类只服务 public/front.php。
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
            $kernel->addControllerInjectedArg(new Lock($this->getService('memcached', \Memcached::class)));
            // 本请求共用这一个 EntityManager，避免状态查询再连一次库
            $em = DoctrineFactory::createEntityManager();
            $repo = $em->getRepository(DailyCheckInUserData::class);
            assert($repo instanceof DailyCheckInUserDataRepository);
            $kernel->addControllerInjectedArg($em);
            // 签到规则仍用原来的 CheckInService。时区是字符串，不能靠类型自动装配
            $kernel->addControllerInjectedArg(new CheckInService(
                $repo,
                DailyCheckInConfig::load(),
                getenv('APP_TIMEZONE') ?: 'Asia/Shanghai',
                $em,
                new LoggingGiftClient(),
            ));

            // 充值活动固定为 2。时区同样是字符串，要显式传入
            $kernel->addControllerInjectedArg(new RechargeService(
                $em,
                new LoggingGiftClient(),
                getenv('APP_TIMEZONE') ?: 'Asia/Shanghai',
            ));
            // 新入口暂时仍校验旧登录接口签发的 JWT，还没换成公司的 RequestSender
            $kernel->addControllerInjectedArg(new JwtService());
            $kernel->addExtraParameters($container->getParameterBag()->all());
            // 假账号登录。JWT 里的 role_id 仍是角色表主键，活动固定为签到活动 1
            $kernel->addControllerInjectedArg(new LoginService($em));
            $this->microKernel = $kernel;
        }

        return $this->microKernel;
    }
}
