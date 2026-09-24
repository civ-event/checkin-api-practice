<?php // PHP 起始

declare(strict_types=1); // 严格类型

namespace Checkin\Database; // 数据库工厂命名空间

use Doctrine\DBAL\DriverManager; // DBAL 连接管理
use Doctrine\ORM\EntityManager; // ORM 实体管理器实现
use Doctrine\ORM\EntityManagerInterface; // 接口类型（方便替换实现）
use Doctrine\ORM\ORMSetup; // ORM 配置构建器
use Symfony\Component\Cache\Adapter\ArrayAdapter; // 进程内元数据缓存（学习用简单）

/** 按环境变量创建 Doctrine EntityManager */
final class DoctrineFactory
{
    public static function createEntityManager(): EntityManagerInterface
    {
        $isDev = (getenv('APP_DEBUG') ?: '0') === '1'; // 开发模式：更勤快刷新元数据

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__) . '/Entities'], // 扫描带 Attribute 的实体目录
            isDevMode: $isDev,
            cache: new ArrayAdapter(), // 不持久化缓存，重启即空
        );
        // PHP 8.4+ 用原生惰性对象做实体代理。不开的话 ORM 会去找 symfony/var-exporter 6/7，和现在的 Symfony 8 对不上
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([ // 建立 MySQL 连接参数
            'driver' => 'pdo_mysql', // 走 PDO MySQL
            'host' => getenv('MYSQL_HOST') ?: 'mysql', // Docker 服务名
            'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
            'dbname' => getenv('MYSQL_DATABASE') ?: 'checkin',
            'user' => getenv('MYSQL_USER') ?: 'checkin',
            'password' => getenv('MYSQL_PASSWORD') ?: 'checkin',
            'charset' => 'utf8mb4', // 支持 emoji / 中文
        ], $config);

        return new EntityManager($connection, $config); // 组装 EM
    }
}
