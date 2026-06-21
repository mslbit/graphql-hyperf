<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf;

use Maiscraft\GraphQL\Engine\GraphQLEngine;
use Maiscraft\GraphQL\Engine\EngineConfig;
use Maiscraft\GraphQL\Contract\CacheInterface as GraphQLCacheInterface;
use Maiscraft\GraphQL\Contract\ValidatorInterface;
use Maiscraft\GraphQL\Contract\LoggerInterface;
use Maiscraft\GraphQL\Contract\ContainerInterface;
use Maiscraft\GraphQL\Contract\EventDispatcherInterface;
use Maiscraft\GraphQL\Contract\RateLimiterInterface;
use Maiscraft\GraphQLHyperf\Contract\HyperfCache;
use Maiscraft\GraphQLHyperf\Contract\HyperfContainer;
use Maiscraft\GraphQLHyperf\Contract\HyperfEventDispatcher;
use Maiscraft\GraphQLHyperf\Contract\HyperfLogger;
use Maiscraft\GraphQLHyperf\Contract\HyperfValidator;
use Maiscraft\GraphQLHyperf\Contract\HyperfRateLimiter;
use Psr\SimpleCache\CacheInterface as HyperfCacheInterface;
use Psr\Log\LoggerInterface as PsrLogger;
use Hyperf\Validation\ValidatorFactory;
use Psr\Container\ContainerInterface as PsrContainerInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                GraphQLCacheInterface::class => static fn(PsrContainerInterface $container) => new HyperfCache(
                    $container->get(HyperfCacheInterface::class),
                    $container->get(\Hyperf\Redis\RedisFactory::class)
                ),
                ContainerInterface::class => static fn(PsrContainerInterface $container) => new HyperfContainer($container),
                EventDispatcherInterface::class => static fn(PsrContainerInterface $container) => new HyperfEventDispatcher(
                    $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class)
                ),
                LoggerInterface::class => static fn(PsrContainerInterface $container) => new HyperfLogger(
                    $container->get(PsrLogger::class)
                ),
                ValidatorInterface::class => static fn(PsrContainerInterface $container) => new HyperfValidator(
                    $container->get(ValidatorFactory::class)
                ),
                RateLimiterInterface::class => static function (PsrContainerInterface $container) {
                    return new HyperfRateLimiter(
                        $container->get(\Hyperf\Redis\RedisFactory::class),
                        $container->get(\Hyperf\Contract\ConfigInterface::class)
                    );
                },

                GraphQLEngine::class => static function (PsrContainerInterface $container) {
                    $config = $container->get(\Hyperf\Contract\ConfigInterface::class);
                    $graphqlConfig = $config->get('graphql', []);

                    $engineConfig = EngineConfig::create()
                        ->setCache($container->get(GraphQLCacheInterface::class))
                        ->setContainer($container->get(ContainerInterface::class))
                        ->setEventDispatcher($container->get(EventDispatcherInterface::class))
                        ->setLogger($container->get(LoggerInterface::class))
                        ->setValidator($container->get(ValidatorInterface::class))
                        ->setRateLimiter($container->get(RateLimiterInterface::class))
                        ->setDebug($graphqlConfig['debug'] ?? false)
                        ->setMaxQueryDepth($graphqlConfig['security']['max_query_depth'] ?? 15)
                        ->setMaxQueryComplexity($graphqlConfig['security']['max_query_complexity'] ?? 1000)
                        ->setRateLimit(
                            $graphqlConfig['rate_limit']['max_attempts'] ?? 100,
                            $graphqlConfig['rate_limit']['decay_seconds'] ?? 60
                        );

                    $engineConfig->setSources($graphqlConfig['sources'] ?? []);
                    $engineConfig->setTypes($graphqlConfig['types'] ?? []);
                    $engineConfig->setScanPaths($graphqlConfig['scan_paths'] ?? []);

                    return new GraphQLEngine($engineConfig);
                },
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'graphql-config',
                    'description' => 'GraphQL configuration file.',
                    'source' => __DIR__ . '/../publish/graphql.php',
                    'destination' => BASE_PATH . '/config/autoload/graphql.php',
                ],
            ],
        ];
    }
}
