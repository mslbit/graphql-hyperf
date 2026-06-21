<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Contract;

use Maiscraft\GraphQL\Contract\RateLimiterInterface;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Hyperf\Contract\ConfigInterface;

/**
 * Hyperf 限流器适配器
 *
 * 基于 Redis 的固定窗口限流实现，适配到 GraphQL 核心层的 RateLimiterInterface。
 * 通过构造函数注入 RedisFactory 和 ConfigInterface（避免 #[Inject] 时序问题）。
 */
class HyperfRateLimiter implements RateLimiterInterface
{
    private Redis $redis;

    public function __construct(
        RedisFactory $redisFactory,
        ConfigInterface $config
    ) {
        $poolName = $config->get('graphql.cache.driver', 'default');
        $this->redis = $redisFactory->get($poolName);
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        $this->cleanupExpired($key);

        $current = $this->count($key);

        if ($current >= $maxAttempts) {
            return false;
        }

        $this->hit($key, $decaySeconds);

        return true;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $this->cleanupExpired($key);
        $current = $this->count($key);

        return max(0, $maxAttempts - $current);
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->remaining($key, $maxAttempts) <= 0;
    }

    public function retryAfter(string $key): int
    {
        $decayKey = $this->getDecayKey($key);
        $decayTime = $this->redis->get($decayKey);

        if ($decayTime === false) {
            return 0;
        }

        $remaining = (int)$decayTime - time();

        return max(0, $remaining);
    }

    public function clear(string $key): void
    {
        $cacheKey = $this->getCacheKey($key);
        $decayKey = $this->getDecayKey($key);

        $this->redis->del([$cacheKey, $decayKey]);
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $cacheKey = $this->getCacheKey($key);
        $decayKey = $this->getDecayKey($key);

        if ($this->redis->get($decayKey) === false) {
            $this->redis->set($decayKey, time() + $decaySeconds, ['EX' => $decaySeconds]);
            $this->redis->set($cacheKey, 0, ['EX' => $decaySeconds]);
        }

        $current = $this->redis->incr($cacheKey);

        return (int)$current;
    }

    public function count(string $key): int
    {
        $cacheKey = $this->getCacheKey($key);
        $value = $this->redis->get($cacheKey);

        return $value === false ? 0 : (int)$value;
    }

    private function cleanupExpired(string $key): void
    {
        $decayKey = $this->getDecayKey($key);
        $decayTime = $this->redis->get($decayKey);

        if ($decayTime !== false && (int)$decayTime < time()) {
            $this->clear($key);
        }
    }

    private function getCacheKey(string $key): string
    {
        return 'rate_limit:' . $key . ':count';
    }

    private function getDecayKey(string $key): string
    {
        return 'rate_limit:' . $key . ':decay';
    }
}
