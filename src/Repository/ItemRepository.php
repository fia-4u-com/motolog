<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Repository;

use Motolog\Support\Db;

/** items テーブル。 */
final class ItemRepository
{
    private const COLUMNS = 'id, vehicle_id, name, kind, measure, initial_value, sort_order, is_active';

    /** @return list<array<string, mixed>> 並び順どおり */
    public function byVehicle(int $vehicleId, bool $activeOnly = false): array
    {
        $st = Db::pdo()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM items WHERE vehicle_id = ?'
            . ($activeOnly ? ' AND is_active = 1' : '')
            . ' ORDER BY sort_order, id'
        );
        $st->execute([$vehicleId]);
        return array_map([self::class, 'cast'], $st->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM items WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row === false ? null : self::cast($row);
    }

    /** 記録（走行記録の品目）のある品目の ID。 @return array<int, true> */
    public function idsWithRecords(int $vehicleId): array
    {
        $st = Db::pdo()->prepare(
            'SELECT DISTINCT rli.item_id FROM ride_log_items rli
               JOIN items i ON i.id = rli.item_id WHERE i.vehicle_id = ?'
        );
        $st->execute([$vehicleId]);
        $ids = [];
        foreach ($st->fetchAll() as $row) {
            $ids[(int) $row['item_id']] = true;
        }
        return $ids;
    }

    /** @param array<string, mixed> $item */
    public function insert(int $vehicleId, array $item): int
    {
        $st = Db::pdo()->prepare(
            'INSERT INTO items (vehicle_id, name, kind, measure, initial_value, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $vehicleId, $item['name'], $item['kind'], $item['measure'], $item['initial_value'],
            $item['sort_order'], $item['is_active'] ? 1 : 0,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $item */
    public function update(int $id, array $item): void
    {
        $st = Db::pdo()->prepare(
            'UPDATE items SET name = ?, kind = ?, measure = ?, initial_value = ?, sort_order = ?, is_active = ? WHERE id = ?'
        );
        $st->execute([
            $item['name'], $item['kind'], $item['measure'], $item['initial_value'],
            $item['sort_order'], $item['is_active'] ? 1 : 0, $id,
        ]);
    }

    public function delete(int $id): void
    {
        $st = Db::pdo()->prepare('DELETE FROM items WHERE id = ?');
        $st->execute([$id]);
    }

    /**
     * 名前の一時的な重複を避けるため、一括保存の前に名前を仮の値にする。
     * （例：品目 A と B の名前を入れ替えるとき、重複禁止の決まりに引っかからないようにする）
     */
    public function renameTemporarily(int $id): void
    {
        $st = Db::pdo()->prepare('UPDATE items SET name = CONCAT(\'__tmp_\', id) WHERE id = ?');
        $st->execute([$id]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function cast(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'vehicle_id' => (int) $row['vehicle_id'],
            'name' => (string) $row['name'],
            'kind' => (string) $row['kind'],
            'measure' => $row['measure'] === null ? null : (string) $row['measure'],
            'initial_value' => $row['initial_value'] === null ? null : (int) $row['initial_value'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) $row['is_active'],
        ];
    }
}
