<?php

declare(strict_types=1);

namespace Checkin;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * 声明 config/config.yml 允许哪些键。
 * 这里没写的键（例如临时加一个 memcached）会在启动时被配置组件拒绝。
 */
class CheckinConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('app');
        $root = $treeBuilder->getRootNode();
        // 打开后容器和路由在文件变化时会重新编译，错误页也会带堆栈
        $root->children()->booleanNode('is_debug')->defaultTrue();

        // 日志、缓存、模板目录。路径写在 config.yml，必须是容器内的绝对路径
        $dir = $root->children()->arrayNode('dir');
        $dir->children()->scalarNode('log');
        $dir->children()->scalarNode('data');
        $dir->children()->scalarNode('cache');
        $dir->children()->scalarNode('template');

        return $treeBuilder;
    }
}
