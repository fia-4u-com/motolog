<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Http;

/** ブラウザへの応答。 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
        private readonly ?string $file = null,
    ) {
    }

    /** 成功：{"ok": true, "data": …} */
    public static function ok(mixed $data = null, int $status = 200): self
    {
        return self::json(['ok' => true, 'data' => $data], $status);
    }

    public static function error(ApiException $e): self
    {
        $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        if ($e->fields !== []) {
            $error['fields'] = $e->fields;
        }
        return self::json(['ok' => false, 'error' => $error], $e->status);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        return new self((string) $body, $status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function redirect(string $location): self
    {
        return new self('', 303, ['Location' => $location]);
    }

    /** ファイルをそのまま返す（車両画像）。 */
    public static function file(string $path, string $contentType): self
    {
        return new self('', 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=86400',
            'Content-Length' => (string) filesize($path),
        ], $path);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->file !== null) {
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}
