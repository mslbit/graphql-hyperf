<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Contract;

use Maiscraft\GraphQL\Contract\ContainerInterface;
use Hyperf\Di\Container as HyperfDiContainer;

class HyperfContainer implements ContainerInterface
{
    private HyperfDiContainer $container;

    public function __construct(HyperfDiContainer $container)
    {
        $this->container = $container;
    }

    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function set(string $id, mixed $service): void
    {
        $this->container->set($id, $service);
    }
}
