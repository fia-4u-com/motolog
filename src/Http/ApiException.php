<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Http;

use Motolog\Support\Msg;
use RuntimeException;

/** 画面に返す失敗（詳細設計書「5_API共通」の失敗の種類）。 */
final class ApiException extends RuntimeException
{
    /** @param array<string, string> $fields 項目ごとのメッセージ（入力チェックの失敗のとき） */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }

    /** @param array<string, string> $fields */
    public static function validation(array $fields, string $message = Msg::VALIDATION): self
    {
        return new self('VALIDATION', 422, $message, $fields);
    }

    public static function conflict(string $message): self
    {
        return new self('CONFLICT', 409, $message);
    }

    public static function notFound(): self
    {
        return new self('NOT_FOUND', 404, Msg::NOT_FOUND);
    }

    public static function unauthenticated(): self
    {
        return new self('UNAUTHENTICATED', 401, Msg::M04);
    }

    public static function csrf(): self
    {
        return new self('CSRF', 403, Msg::CSRF);
    }

    public static function tooMany(): self
    {
        return new self('TOO_MANY_ATTEMPTS', 429, Msg::M03);
    }
}
