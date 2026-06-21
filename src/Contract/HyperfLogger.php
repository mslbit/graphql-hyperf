<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Contract;

use Maiscraft\GraphQL\Contract\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Hyperf 日志适配器
 * 
 * 将 Hyperf Logger 适配到 GraphQL 核心层的日志接口
 */
class HyperfLogger implements LoggerInterface
{
    private PsrLoggerInterface $logger;

    public function __construct(PsrLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 记录调试日志
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * 记录信息日志
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * 记录警告日志
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * 记录错误日志
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}