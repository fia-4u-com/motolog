<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Http;

/** ブラウザからの要求。 */
final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $json = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, string> $params URL の {id} などの値（ルーターが入れる）
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly string $ip,
        private readonly string $rawBody,
        private readonly ?string $csrfHeader,
        public array $params = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? rtrim($path, '/') : '';
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path === '' ? '/' : $path,
            $_GET,
            $_POST,
            $_FILES,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) file_get_contents('php://input'),
            isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : null,
        );
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    /** JSON の本文。形が正しくなければ入力チェックの失敗にする。 @return array<string, mixed> */
    public function json(): array
    {
        if ($this->json === null) {
            $data = json_decode($this->rawBody === '' ? '{}' : $this->rawBody, true);
            if (!is_array($data)) {
                throw ApiException::validation([]);
            }
            $this->json = $data;
        }
        return $this->json;
    }

    /** CSRF の合言葉。API はヘッダー、画面のフォームは隠し項目から。 */
    public function csrfToken(): ?string
    {
        return $this->csrfHeader ?? (isset($this->post['csrf']) ? (string) $this->post['csrf'] : null);
    }

    public function intParam(string $name): int
    {
        return (int) ($this->params[$name] ?? 0);
    }
}
