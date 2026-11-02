<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Repository;

use Motolog\Support\Db;

/** ride_logs と ride_log_items テーブル。 */
final class RideLogRepository
{
    /**
     * 車両のすべての走行記録（日付の古い順）。品目の交換・増減も付ける。
     *
     * @return list<array{id: int, date: string, odo: int, comment: string, replaced: list<int>, deltas: array<int, int>}>
     */
    public function byVehicle(int $vehicleId): array
    {
        $st = Db::pdo()->prepare('SELECT id, ride_date, odo, comment FROM ride_logs WHERE vehicle_id = ? ORDER BY ride_date');
        $st->execute([$vehicleId]);
        $logs = [];
        foreach ($st->fetchAll() as $row) {
            $logs[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'date' => (string) $row['ride_date'],
                'odo' => (int) $row['odo'],
                'comment' => (string) $row['comment'],
                'replaced' => [],
                'deltas' => [],
            ];
        }

        $st = Db::pdo()->prepare(
            'SELECT rli.ride_log_id, rli.item_id, rli.delta FROM ride_log_items rli
               JOIN ride_logs rl ON rl.id = rli.ride_log_id WHERE rl.vehicle_id = ?'
        );
        $st->execute([$vehicleId]);
        foreach ($st->fetchAll() as $row) {
            $logId = (int) $row['ride_log_id'];
            if (!isset($logs[$logId])) {
                continue;
            }
            if ($row['delta'] === null) {
                $logs[$logId]['replaced'][] = (int) $row['item_id'];
            } else {
                $logs[$logId]['deltas'][(int) $row['item_id']] = (int) $row['delta'];
            }
        }
        return array_values($logs);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, vehicle_id, ride_date, odo, comment FROM ride_logs WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function dateExists(int $vehicleId, string $date, ?int $excludeId): bool
    {
        $st = Db::pdo()->prepare('SELECT COUNT(*) FROM ride_logs WHERE vehicle_id = ? AND ride_date = ? AND id <> ?');
        $st->execute([$vehicleId, $date, $excludeId ?? 0]);
        return (int) $st->fetchColumn() > 0;
    }

    /** 指定した日付の直前・直後の記録（自分自身は除く）。 @return array{0: ?array, 1: ?array} */
    public function neighbors(int $vehicleId, string $date, ?int $excludeId): array
    {
        $prev = Db::pdo()->prepare(
            'SELECT ride_date, odo FROM ride_logs WHERE vehicle_id = ? AND ride_date < ? AND id <> ? ORDER BY ride_date DESC LIMIT 1'
        );
        $prev->execute([$vehicleId, $date, $excludeId ?? 0]);
        $next = Db::pdo()->prepare(
            'SELECT ride_date, odo FROM ride_logs WHERE vehicle_id = ? AND ride_date > ? AND id <> ? ORDER BY ride_date LIMIT 1'
        );
        $next->execute([$vehicleId, $date, $excludeId ?? 0]);
        $p = $prev->fetch();
        $n = $next->fetch();
        return [$p === false ? null : $p, $n === false ? null : $n];
    }

    public function insert(int $vehicleId, string $date, int $odo, string $comment): int
    {
        $st = Db::pdo()->prepare('INSERT INTO ride_logs (vehicle_id, ride_date, odo, comment) VALUES (?, ?, ?, ?)');
        $st->execute([$vehicleId, $date, $odo, $comment]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, string $date, int $odo, string $comment): void
    {
        $st = Db::pdo()->prepare('UPDATE ride_logs SET ride_date = ?, odo = ?, comment = ? WHERE id = ?');
        $st->execute([$date, $odo, $comment, $id]);
    }

    public function delete(int $id): void
    {
        $st = Db::pdo()->prepare('DELETE FROM ride_logs WHERE id = ?');
        $st->execute([$id]);
    }

    /**
     * 走行記録の品目を、指定した内容で置き換える。
     *
     * @param list<int> $replaced 交換した品目の ID
     * @param array<int, int> $deltas 設定値の品目ごとの増減（0 は入れない）
     */
    public function replaceItems(int $logId, array $replaced, array $deltas): void
    {
        $del = Db::pdo()->prepare('DELETE FROM ride_log_items WHERE ride_log_id = ?');
        $del->execute([$logId]);
        $ins = Db::pdo()->prepare('INSERT INTO ride_log_items (ride_log_id, item_id, delta) VALUES (?, ?, ?)');
        foreach ($replaced as $itemId) {
            $ins->execute([$logId, $itemId, null]);
        }
        foreach ($deltas as $itemId => $delta) {
            $ins->execute([$logId, $itemId, $delta]);
        }
    }
}
