<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

use DateTimeImmutable;

/** 経過年月日（詳細設計書 3_計算仕様 C-01）。 */
final class Elapsed
{
    /**
     * $from から $to までの年・月・日。$to が $from より前なら 0。
     *
     * @return array{y: int, m: int, d: int}
     */
    public static function between(string $from, string $to): array
    {
        $a = new DateTimeImmutable($from);
        $b = new DateTimeImmutable($to);
        if ($b < $a) {
            return ['y' => 0, 'm' => 0, 'd' => 0];
        }
        $diff = $a->diff($b);
        return ['y' => $diff->y, 'm' => $diff->m, 'd' => $diff->d];
    }

    /**
     * 表示の形式（例：2年07ヶ月05日、3ヶ月12日、5日、0日）。
     * 画面側（format.js）と同じ決まり。検証や単体テストで使う。
     *
     * @param array{y: int, m: int, d: int} $e
     */
    public static function format(array $e): string
    {
        $text = '';
        if ($e['y'] > 0) {
            $text .= $e['y'] . '年';
        }
        if ($e['m'] > 0) {
            $text .= ($e['y'] > 0 ? sprintf('%02d', $e['m']) : (string) $e['m']) . 'ヶ月';
        }
        if ($e['d'] > 0) {
            $text .= (($e['y'] > 0 || $e['m'] > 0) ? sprintf('%02d', $e['d']) : (string) $e['d']) . '日';
        }
        return $text === '' ? '0日' : $text;
    }
}
