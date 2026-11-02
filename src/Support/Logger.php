<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Support;

use Throwable;

/**
 * storage/logs/app.log に1行1件で記録する。
 * パスワード・セッションID・CSRFの合言葉は書かない（N-14）。呼び出し側で渡さないこと。
 */
final class Logger
{
    public static function error(string $message, ?Throwable $e = null): void
    {
        $line = sprintf('[%s] ERROR %s', date('Y-m-d H:i:s'), $message);
        if ($e !== null) {
            $line .= sprintf(' | %s: %s @ %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
        }
        self::write($line);
    }

    public static function info(string $message): void
    {
        self::write(sprintf('[%s] INFO %s', date('Y-m-d H:i:s'), $message));
    }

    private static function write(string $line): void
    {
        $dir = APP_ROOT . '/storage/logs';
        if (is_dir($dir) && is_writable($dir)) {
            error_log(str_replace(["\r", "\n"], ' ', $line) . PHP_EOL, 3, $dir . '/app.log');
        } else {
            error_log($line);
        }
    }
}
