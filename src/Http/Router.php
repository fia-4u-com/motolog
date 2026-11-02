<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Http;

/**
 * URL と処理の対応。ログインの確認と CSRF の確認はここで必ず行う（N-12／N-16／N-20）。
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable, auth: bool}> */
    private array $routes = [];

    /** @param callable(Request): Response $handler */
    public function add(string $method, string $pattern, callable $handler, bool $auth = true): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'auth' => $auth];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $m) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($route['auth'] && Session::userId() === null) {
                throw ApiException::unauthenticated();
            }
            if ($request->method !== 'GET' && !Session::checkCsrf($request->csrfToken())) {
                throw ApiException::csrf();
            }
            return ($route['handler'])($request);
        }
        if ($pathMatched) {
            return Response::json(['ok' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method Not Allowed']], 405);
        }
        throw ApiException::notFound();
    }
}
