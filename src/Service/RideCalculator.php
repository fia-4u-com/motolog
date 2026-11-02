<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

/**
 * 走行記録の計算（詳細設計書 3_計算仕様 C-01〜C-07b）。DB を使わない純粋な計算だけを行う。
 *
 * 入力の形：
 *   $vehicle = ['acquired_on' => 'YYYY-MM-DD', 'first_registered_on' => 'YYYY-MM-DD'|null]
 *   $items   = [['id' => int, 'name' => string, 'kind' => 'replace'|'setting', 'measure' => 'distance'|'period'|null, 'initial_value' => ?int], …]
 *   $logs    = [['id' => int, 'date' => 'YYYY-MM-DD', 'odo' => int, 'comment' => string, 'replaced' => list<int>, 'deltas' => array<int, int>], …]（日付の古い順）
 */
final class RideCalculator
{
    /**
     * 一覧の各行の計算結果。
     *
     * @param array<string, mixed> $vehicle
     * @param list<array<string, mixed>> $items 表示する品目（並び順どおり）
     * @param list<array<string, mixed>> $logs
     * @return list<array<string, mixed>>
     */
    public static function rows(array $vehicle, array $items, array $logs): array
    {
        $origin = self::origin($vehicle);
        $last = [];        // 品目ID => ['date' => …, 'odo' => …]（最後に交換した記録）
        $values = [];      // 設定値の品目ID => その行までの値
        foreach ($items as $item) {
            if ($item['kind'] === 'setting') {
                $values[$item['id']] = (int) $item['initial_value'];
            }
        }

        $rows = [];
        $prevOdo = null;
        foreach ($logs as $log) {
            $cells = [];
            $events = [];   // その回にした整備（C-03〜C-05。一覧の「整備内容」）
            foreach ($items as $item) {
                $id = $item['id'];
                if ($item['kind'] === 'setting') {
                    $delta = (int) ($log['deltas'][$id] ?? 0);
                    $values[$id] += $delta;
                    $cells[$id] = ['type' => 'setting', 'delta' => $delta, 'value' => $values[$id]];
                    if ($delta !== 0) {
                        $events[] = ['item_id' => $id, 'name' => $item['name']] + $cells[$id];
                    }
                } elseif (in_array($id, $log['replaced'], true)) {
                    $cells[$id] = ['type' => 'replaced'];
                    // 前回の交換（なければ新車時）から、この交換までの距離または期間
                    $since = self::sinceLast($item, $last[$id] ?? null, $log['date'], $log['odo'], $origin);
                    $events[] = [
                        'item_id' => $id,
                        'name' => $item['name'],
                        'type' => $since['type'],
                        'value' => $since['value'],
                        'from_new' => $since['unreplaced'],
                    ];
                } else {
                    $cells[$id] = self::sinceLast($item, $last[$id] ?? null, $log['date'], $log['odo'], $origin);
                }
            }
            foreach ($log['replaced'] as $id) {
                $last[$id] = ['date' => $log['date'], 'odo' => $log['odo']];
            }

            $rows[] = [
                'id' => $log['id'],
                'date' => $log['date'],
                'odo' => $log['odo'],
                'elapsed' => Elapsed::between($vehicle['acquired_on'], $log['date']),
                'diff' => $prevOdo === null ? null : $log['odo'] - $prevOdo,
                'comment' => $log['comment'],
                'cells' => (object) $cells,
                'events' => $events,
                'replaced' => $log['replaced'],
                'deltas' => (object) $log['deltas'],
            ];
            $prevOdo = $log['odo'];
        }
        return $rows;
    }

    /**
     * 最終交換状況（一覧の上に常に表示する。C-06／C-07／C-07b）。
     *
     * @param array<string, mixed> $vehicle
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $logs
     * @return list<array<string, mixed>>
     */
    public static function status(array $vehicle, array $items, array $logs, ?int $currentOdo, string $today): array
    {
        $origin = self::origin($vehicle);
        $result = [];
        foreach ($items as $item) {
            $id = $item['id'];
            $entry = ['item_id' => $id, 'name' => $item['name'], 'kind' => $item['kind'], 'measure' => $item['measure']];
            if ($item['kind'] === 'setting') {
                $value = (int) $item['initial_value'];
                $lastLogId = null;   // 最後に値を変えた記録（札を押したときの移動先。S02）
                foreach ($logs as $log) {
                    $delta = (int) ($log['deltas'][$id] ?? 0);
                    $value += $delta;
                    if ($delta !== 0) {
                        $lastLogId = $log['id'];
                    }
                }
                $result[] = $entry + ['value' => $value, 'initial' => (int) $item['initial_value'], 'last_log_id' => $lastLogId];
                continue;
            }
            $lastLog = null;
            foreach ($logs as $log) {
                if (in_array($id, $log['replaced'], true)) {
                    $lastLog = $log;
                }
            }
            $unreplaced = $lastLog === null;
            $entry['last_log_id'] = $lastLog['id'] ?? null;
            if ($item['measure'] === 'period') {
                $result[] = $entry + [
                    'last_date' => $lastLog['date'] ?? null,
                    'last_odo' => $lastLog['odo'] ?? null,
                    'period' => Elapsed::between($unreplaced ? $origin : $lastLog['date'], $today),
                    'unreplaced' => $unreplaced,
                ];
            } else {
                $result[] = $entry + [
                    'last_date' => $lastLog['date'] ?? null,
                    'last_odo' => $lastLog['odo'] ?? null,
                    'distance' => $currentOdo === null ? null : $currentOdo - ($unreplaced ? 0 : (int) $lastLog['odo']),
                    'unreplaced' => $unreplaced,
                ];
            }
        }
        return $result;
    }

    /**
     * 未交換の品目の期間の起点：初度登録年月（なければ取得日）。D10 案B。
     *
     * @param array<string, mixed> $vehicle
     */
    private static function origin(array $vehicle): string
    {
        $first = $vehicle['first_registered_on'] ?? null;
        return is_string($first) && $first !== '' ? $first : (string) $vehicle['acquired_on'];
    }

    /**
     * 前回の交換からの距離または期間（C-03／C-04）。交換の記録がなければ新車時から。
     *
     * @param array<string, mixed> $item
     * @param array{date: string, odo: int}|null $last
     * @return array<string, mixed>
     */
    private static function sinceLast(array $item, ?array $last, string $date, int $odo, string $origin): array
    {
        $unreplaced = $last === null;
        if ($item['measure'] === 'period') {
            return [
                'type' => 'period',
                'value' => Elapsed::between($unreplaced ? $origin : $last['date'], $date),
                'unreplaced' => $unreplaced,
            ];
        }
        return [
            'type' => 'distance',
            'value' => $unreplaced ? $odo : $odo - $last['odo'],
            'unreplaced' => $unreplaced,
        ];
    }
}
