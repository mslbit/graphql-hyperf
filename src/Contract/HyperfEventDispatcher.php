<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Contract;

use Maiscraft\GraphQL\Contract\EventDispatcherInterface;
use Maiscraft\GraphQL\Event\EventInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

class HyperfEventDispatcher implements EventDispatcherInterface
{
    private PsrEventDispatcherInterface $dispatcher;

    public function __construct(PsrEventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch(EventInterface $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    public function addListener(string $eventName, callable $listener): void
    {
        throw new \RuntimeException(
            'Hyperf event system does not support runtime listener registration. ' .
            'Please use #[Listener] annotation or config/autoload/listeners.php to register listeners.'
        );
    }
}
