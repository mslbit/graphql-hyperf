<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Controller;

use Maiscraft\GraphQL\Engine\GraphQLEngine;
use Maiscraft\GraphQL\Error\GraphQLError;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * GraphQL HTTP 控制器
 *
 * 处理 GraphQL HTTP 请求，支持：
 * - POST /graphql：执行 GraphQL 查询
 * - GET /graphql：Introspection 查询
 * - GET /playground：GraphQL Playground 调试界面
 */
#[Controller(prefix: '/')]
#[Middleware(\App\Middleware\CorsMiddleware::class)]
class GraphQLController
{
    private GraphQLEngine $engine;

    public function __construct(GraphQLEngine $engine)
    {
        $this->engine = $engine;
    }

    #[RequestMapping(path: 'graphql', methods: 'POST')]
    public function index(RequestInterface $request, ResponseInterface $response): PsrResponseInterface
    {
        try {
            $body = json_decode($request->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $response->json([
                    'errors' => [['message' => 'Invalid JSON payload']],
                ])->withStatus(400);
            }

            $query = $body['query'] ?? '';
            $variables = $body['variables'] ?? [];
            $operationName = $body['operationName'] ?? null;

            if (empty(trim($query))) {
                return $response->json([
                    'errors' => [['message' => 'Query cannot be empty']],
                ])->withStatus(400);
            }

            $result = $this->engine->execute($query, $variables, $operationName);

            $statusCode = isset($result['errors']) ? 200 : 200;

            return $response->json($result)->withStatus($statusCode);
        } catch (\Throwable $e) {
            return $response->json([
                'errors' => [['message' => $e->getMessage()]],
            ])->withStatus(500);
        }
    }

    #[RequestMapping(path: 'graphql', methods: 'GET')]
    public function introspect(RequestInterface $request, ResponseInterface $response): PsrResponseInterface
    {
        try {
            $query = $request->input('query', '');

            if (empty(trim($query))) {
                $query = \GraphQL\Type\Introspection::getIntrospectionQuery();
            }

            $variables = json_decode($request->input('variables', '{}'), true) ?? [];
            $operationName = $request->input('operationName');

            $result = $this->engine->execute($query, $variables, $operationName);

            return $response->json($result);
        } catch (\Throwable $e) {
            return $response->json([
                'errors' => [['message' => $e->getMessage()]],
            ])->withStatus(500);
        }
    }

    #[RequestMapping(path: 'playground', methods: 'GET')]
    public function playground(ResponseInterface $response): PsrResponseInterface
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset=utf-8/>
    <title>GraphQL Playground</title>
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/graphql-playground-react/build/static/css/index.css"/>
    <script src="//cdn.jsdelivr.net/npm/graphql-playground-react/build/static/js/middleware.js"></script>
</head>
<body>
    <div id="root"/>
    <script>
        GraphQLPlayground.init(document.getElementById('root'), {
            endpoint: '/graphql'
        })
    </script>
</body>
</html>
HTML;

        return $response->html($html);
    }
}
