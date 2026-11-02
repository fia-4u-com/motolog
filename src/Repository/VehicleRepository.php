<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Repository;

use Motolog\Support\Db;

/** vehicles テーブル。 */
final class VehicleRepository
{
    private const COLUMNS = 'id, name, vin, plate, acquired_on, first_registered_on, image_file, status, sort_order';

    /** @return list<array<string, mixed>> */
    public function all(bool $includeRetired): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM vehicles'
            . ($includeRetired ? '' : " WHERE status = 'active'")
            . ' ORDER BY sort_order, id';
        return Db::pdo()->query($sql)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM vehicles WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $v */
    public function insert(array $v): int
    {
        $next = (int) Db::pdo()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM vehicles')->fetchColumn();
        $st = Db::pdo()->prepare(
            'INSERT INTO vehicles (name, vin, plate, acquired_on, first_registered_on, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$v['name'], $v['vin'], $v['plate'], $v['acquired_on'], $v['first_registered_on'], $v['status'], $next]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $v */
    public function update(int $id, array $v): void
    {
        $st = Db::pdo()->prepare(
            'UPDATE vehicles SET name = ?, vin = ?, plate = ?, acquired_on = ?, first_registered_on = ?, status = ? WHERE id = ?'
        );
        $st->execute([$v['name'], $v['vin'], $v['plate'], $v['acquired_on'], $v['first_registered_on'], $v['status'], $id]);
    }

    public function setImage(int $id, ?string $file): void
    {
        $st = Db::pdo()->prepare('UPDATE vehicles SET image_file = ? WHERE id = ?');
        $st->execute([$file, $id]);
    }

    /** その車両の最初の走行記録（日付が最も古いもの）。 @return array<string, mixed>|null */
    public function firstRideLog(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT ride_date, odo FROM ride_logs WHERE vehicle_id = ? ORDER BY ride_date LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }
}
