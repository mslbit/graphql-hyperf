# Maiscraft GraphQL Hyperf Adapter

[maiscraft/graphql](https://github.com/mslbit/graphql) 的 Hyperf 框架适配包，提供自动装配、PSR 容器桥接、HTTP 控制器和配置发布。

## 功能

- **ConfigProvider**：自动注册 GraphQLEngine 及所有依赖到 Hyperf DI 容器
- **HyperfContainer**：PSR-11 容器桥接，将 Hyperf DI 适配为 `Maiscraft\GraphQL\Contract\ContainerInterface`
- **GraphQLController**：处理 `POST /graphql`、`GET /graphql`、`GET /playground` 请求
- **适配器**：Cache、Logger、EventDispatcher、Validator、RateLimiter 全部桥接到 Hyperf 对应组件
- **配置发布**：`php bin/hyperf.php vendor:publish maiscraft/graphql-hyperf`

## 安装

```bash
composer require maiscraft/graphql
composer require maiscraft/graphql-hyperf
```

## 配置

安装后发布配置文件：

```bash
php bin/hyperf.php vendor:publish maiscraft/graphql-hyperf
```

生成 `config/autoload/graphql.php`：

```php
return [
    'debug' => env('GRAPHQL_DEBUG', true),

    // 显式指定 Resolver 类
    'sources' => [
        // App\GraphQL\ArticleResolver::class,
    ],

    // 目录路径扫描（从文件内容解析 namespace 获取类名）
    'scan_paths' => [
        BASE_PATH . '/app',
    ],

    'security' => [
        'max_query_depth' => 15,
        'max_query_complexity' => 1000,
    ],

    'rate_limit' => [
        'max_attempts' => 100,
        'decay_seconds' => 60,
    ],
];
```

## 使用

定义 Entity、Enum、DTO、Resolver 后（参见 [maiscraft/graphql](https://github.com/mslbit/graphql)），Hyperf 自动发现并构建 Schema：

```php
namespace App\GraphQL;

use App\Domain\Entity\Article;
use Maiscraft\GraphQL\Annotation\Query;
use Maiscraft\GraphQL\Annotation\Arg;
use Maiscraft\GraphQL\Contract\ResolverInterface;

class ArticleResolver implements ResolverInterface
{
    public function __construct(
        private ArticleRepositoryInterface $repository
    ) {}

    #[Query(description: 'Get an article')]
    public function article(#[Arg(type: 'ID!')] string $id): ?Article
    {
        return $this->repository->findById((int) $id);
    }
}
```

查询：

```bash
curl -X POST http://localhost:9501/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ article(id: \"1\") { id title } }"}'
```

## API 端点

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/graphql` | 执行 GraphQL 查询 |
| GET | `/graphql` | Introspection 查询 |
| GET | `/playground` | GraphQL Playground 调试界面 |

## 项目结构

```
src/
├── ConfigProvider.php        # Hyperf 服务注册
├── Contract/                 # Hyperf 组件适配器
│   ├── HyperfCache.php
│   ├── HyperfContainer.php
│   ├── HyperfEventDispatcher.php
│   ├── HyperfLogger.php
│   ├── HyperfRateLimiter.php
│   └── HyperfValidator.php
├── Controller/
│   └── GraphQLController.php # HTTP 控制器
publish/
└── graphql.php               # 配置模板
```

## 依赖

- PHP ^8.2
- Hyperf ^3.1
- [maiscraft/graphql](https://github.com/mslbit/graphql) ^1.0