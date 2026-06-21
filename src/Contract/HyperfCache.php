<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Contract;

use Maiscraft\GraphQL\Contract\CacheInterface;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;

/**
 * Hyperf 缓存适配器
 *
 * 将 Hyperf PSR-16 缓存适配到 GraphQL 核心层的 CacheInterface。
 * 基础操作（get/set/delete/has）委托给 PSR-16 实现，
 * 高级操作（clearTags/clearPrefix）通过 Redis keys 扫描实现。
 *
 * 注意：clearTags/clearPrefix 需要 RedisFactory，未注入时返回 false
 */
class HyperfCache implements CacheInterface
{
    private PsrCacheInterface $hyperfCache;
    private ?Redis $redis = null;

    public function __construct(
        PsrCacheInterface $hyperfCache,
        ?RedisFactory $redisFactory = null
    ) {
        $this->hyperfCache = $hyperfCache;
        if ($redisFactory !== null) {
            $this->redis = $redisFactory->get('default');
        }
    }

    public function get(string $key): mixed
    {
        return $this->hyperfCache->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->hyperfCache->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->hyperfCache->delete($key);
    }

    public function clearTags(array $tags): bool
    {
        if ($this->redis === null) {
            return false;
        }

        foreach ($tags as $tag) {
            $keys = $this->redis->keys("graphql:tag:{$tag}:*");
            foreach ($keys as $key) {
                $this->redis->del([$key]);
            }
        }

        return true;
    }

    public function clearPrefix(string $prefix): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $keys = $this->redis->keys("{$prefix}*");
        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->hyperfCache->has($key);
    }
}
