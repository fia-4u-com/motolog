<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Support;

use DateTimeImmutable;

/** 入力値の形のチェック。正しくなければ null を返す。 */
final class Validate
{
    /** 「YYYY-MM-DD」の実在する日付か。 */
    public static function date(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return ($d !== false && $d->format('Y-m-d') === $value) ? $value : null;
    }

    /** 「YYYY-MM」の年月か。月の1日の日付（YYYY-MM-01）にして返す。 */
    public static function month(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return null;
        }
        return self::date($value . '-01');
    }

    /** 範囲内の整数か（"12" のような文字列も受け付ける。"+2" も可）。 */
    public static function int(mixed $value, int $min, int $max): ?int
    {
        if (is_int($value)) {
            $n = $value;
        } elseif (is_string($value) && preg_match('/^[+-]?\d{1,9}$/', trim($value)) === 1) {
            $n = (int) trim($value);
        } elseif (is_float($value) && floor($value) === $value) {
            $n = (int) $value;
        } else {
            return null;
        }
        return ($n >= $min && $n <= $max) ? $n : null;
    }

    /**
     * 給油量（0.01〜99.99、小数第2位まで）か。
     * 誤差を避けるため、1/100 L 単位の整数（センチリットル）にして返す。
     */
    public static function centiliters(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $value = number_format((float) $value, 2, '.', '');
        }
        if (!is_string($value) || preg_match('/^(\d{1,2})(?:\.(\d{1,2}))?$/', trim($value), $m) !== 1) {
            return null;
        }
        $cl = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
        return ($cl >= 1 && $cl <= 9999) ? $cl : null;
    }

    /** 前後の空白を除いた文字列。長すぎれば null。 */
    public static function text(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            $value = '';
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return mb_strlen($value) <= $maxLength ? $value : null;
    }

    public static function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
    }
}
