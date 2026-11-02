<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Repository;

use Motolog\Support\Db;

/** fuel_logs テーブル。給油量は 1/100 L 単位の整数（centiliters）でやり取りする。 */
final class FuelLogRepository
{
    /** @return list<array{id: int, odo: int, centiliters: int, is_full: bool}> ODO の小さい順 */
    public function byVehicle(int $vehicleId): array
    {
        $st = Db::pdo()->prepare('SELECT id, odo, liters, is_full FROM fuel_logs WHERE vehicle_id = ? ORDER BY odo');
        $st->execute([$vehicleId]);
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'odo' => (int) $r['odo'],
            'centiliters' => (int) round(((float) $r['liters']) * 100),
            'is_full' => (bool) $r['is_full'],
        ], $st->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, vehicle_id, odo, liters, is_full FROM fuel_logs WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function odoExists(int $vehicleId, int $odo, ?int $excludeId): bool
    {
        $st = Db::pdo()->prepare('SELECT COUNT(*) FROM fuel_logs WHERE vehicle_id = ? AND odo = ? AND id <> ?');
        $st->execute([$vehicleId, $odo, $excludeId ?? 0]);
        return (int) $st->fetchColumn() > 0;
    }

    public function insert(int $vehicleId, int $odo, int $centiliters, bool $isFull): int
    {
        $st = Db::pdo()->prepare('INSERT INTO fuel_logs (vehicle_id, odo, liters, is_full) VALUES (?, ?, ?, ?)');
        $st->execute([$vehicleId, $odo, self::liters($centiliters), $isFull ? 1 : 0]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $id, int $odo, int $centiliters, bool $isFull): void
    {
        $st = Db::pdo()->prepare('UPDATE fuel_logs SET odo = ?, liters = ?, is_full = ? WHERE id = ?');
        $st->execute([$odo, self::liters($centiliters), $isFull ? 1 : 0, $id]);
    }

    public function delete(int $id): void
    {
        $st = Db::pdo()->prepare('DELETE FROM fuel_logs WHERE id = ?');
        $st->execute([$id]);
    }

    /** 1234 → "12.34" */
    private static function liters(int $centiliters): string
    {
        return sprintf('%d.%02d', intdiv($centiliters, 100), $centiliters % 100);
    }
}
