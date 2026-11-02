<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Support;

use RuntimeException;

/** config/config.php の値を読む。 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('設定ファイルがありません: config/config.php');
        }
        $values = require $file;
        if (!is_array($values)) {
            throw new RuntimeException('設定ファイルの形式が正しくありません');
        }
        self::$values = $values;
    }

    /** 「db.name」のように、ドットで区切って入れ子の値を取り出す。 */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function debug(): bool
    {
        return (bool) self::get('debug', false);
    }
}
