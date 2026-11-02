<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

/**
 * 給油記録とサマリの計算（詳細設計書 3_計算仕様 C-08〜C-15）。DB を使わない純粋な計算だけを行う。
 * 給油量は誤差を避けるため 1/100 L 単位の整数（centiliters）で計算する。
 *
 * $fuels = [['id' => int, 'odo' => int, 'centiliters' => int, 'is_full' => bool], …]（ODO の小さい順）
 * $startOdo = 起点（その車両の最初の走行記録の ODO。なければ 0。C-08）
 */
final class FuelCalculator
{
    /**
     * @param list<array<string, mixed>> $fuels
     * @return array{rows: list<array<string, mixed>>, total_liters: float, lifetime_economy: ?float, max_economy: ?float, min_economy: ?float}
     */
    public static function calculate(array $fuels, int $startOdo): array
    {
        $rows = [];
        $prevOdo = $startOdo;
        $prevFullOdo = $startOdo;
        $sinceFull = 0;        // 前回の満タンの次からの給油量
        $total = 0;            // すべての給油量
        $untilLastFull = 0;    // 最後の満タンまでの給油量
        $lastFullOdo = null;
        $economies = [];

        foreach ($fuels as $f) {
            $cl = (int) $f['centiliters'];
            $total += $cl;
            $sinceFull += $cl;
            $economy = null;
            if ($f['is_full']) {
                $economy = self::economy($f['odo'] - $prevFullOdo, $sinceFull);
                if ($economy !== null) {
                    $economies[] = $economy;
                }
                $prevFullOdo = $f['odo'];
                $sinceFull = 0;
                $untilLastFull = $total;
                $lastFullOdo = $f['odo'];
            }
            $rows[] = [
                'id' => $f['id'],
                'odo' => $f['odo'],
                'liters' => $cl / 100.0,
                'is_full' => (bool) $f['is_full'],
                'diff' => $f['odo'] - $prevOdo,
                'economy' => $economy,
            ];
            $prevOdo = $f['odo'];
        }

        return [
            'rows' => $rows,
            'total_liters' => $total / 100.0,
            'lifetime_economy' => $lastFullOdo === null ? null : self::economy($lastFullOdo - $startOdo, $untilLastFull),
            'max_economy' => $economies === [] ? null : max($economies),
            'min_economy' => $economies === [] ? null : min($economies),
        ];
    }

    /** 燃費（km/L）。小数第3位を四捨五入して第2位まで。計算できなければ null。 */
    public static function economy(int $distance, int $centiliters): ?float
    {
        if ($centiliters <= 0 || $distance < 0) {
            return null;
        }
        // 燃費×100 = distance×10000 / centiliters を、整数のまま四捨五入する
        $x100 = intdiv($distance * 10000 * 2 + $centiliters, 2 * $centiliters);
        return $x100 / 100.0;
    }
}
