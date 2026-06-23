<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Controller;

use Maiscraft\GraphQL\Engine\GraphQLEngine;
use Maiscraft\Rbac\AuthManager;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * GraphQL HTTP 控制器
 *
 * 处理 GraphQL HTTP 请求，支持：
 * - POST /graphql：执行 GraphQL 查询
 * - GET /graphql：Introspection 查询
 * - GET /playground：GraphQL Playground 调试界面
 *
 * 从 Request 提取 Authorization header，通过 AuthManager/Guard 解码 token，
 * 将认证结果（auth_user_id）注入 GraphQL context，供 Mutation resolver 检查
 */
#[Controller(prefix: '/')]
class GraphQLController
{
    private GraphQLEngine $engine;
    private AuthManager $authManager;

    public function __construct(GraphQLEngine $engine, AuthManager $authManager)
    {
        $this->engine = $engine;
        $this->authManager = $authManager;
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

            /** 构造 GraphQL 上下文：携带已认证的用户 ID */
            $context = $this->buildContext($request);

            $result = $this->engine->execute($query, $variables, $operationName, $context);

            return $response->json($result)->withStatus(200);
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

            /** GET 请求（introspection）不需要认证上下文 */
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

    /**
     * 从 HTTP 请求构造 GraphQL 上下文
     *
     * 提取 Authorization header 中的 Bearer token，
     * 通过 AuthManager/Guard 解码验证，将认证用户 ID 注入 context。
     * Mutation resolver 检查 context['auth_user_id'] 判断是否已认证
     */
    private function buildContext(RequestInterface $request): array
    {
        $authorization = $request->getHeaderLine('authorization');
        $token = null;
        $userId = null;

        /** 提取 Bearer token */
        if (str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);
        }

        /** 通过 Guard 解码 token 获取用户 ID */
        if ($token !== null && $token !== '') {
            $guard = $this->authManager->guard();
            if (method_exists($guard, 'decodeToken')) {
                $userId = $guard->decodeToken($token);
            }
        }

        return [
            'token' => $token,
            'auth_user_id' => $userId,
        ];
    }
}
