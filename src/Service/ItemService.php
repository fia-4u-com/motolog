<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

use Motolog\Http\ApiException;
use Motolog\Repository\ItemRepository;
use Motolog\Support\Db;
use Motolog\Support\Msg;
use Motolog\Support\Validate;

/** 品目の設定（F-42／D04）。 */
final class ItemService
{
    public function __construct(
        private readonly ItemRepository $items = new ItemRepository(),
        private readonly VehicleService $vehicles = new VehicleService(),
    ) {
    }

    /** 品目の一覧（記録の有無つき）。 @return list<array<string, mixed>> */
    public function list(int $vehicleId): array
    {
        $this->vehicles->mustFind($vehicleId);
        $withRecords = $this->items->idsWithRecords($vehicleId);
        return array_map(
            static fn (array $i): array => $i + ['has_records' => isset($withRecords[$i['id']])],
            $this->items->byVehicle($vehicleId)
        );
    }

    /**
     * 品目の一覧をまとめて保存する（追加・変更・並べ替え・使用停止。A07）。
     * 送られた順番がそのまま並び順になる。送られなかった品目は変更しない（削除は A08）。
     *
     * @param mixed $list
     */
    public function saveAll(int $vehicleId, mixed $list): void
    {
        $this->vehicles->mustFind($vehicleId);
        if (!is_array($list) || !array_is_list($list)) {
            throw ApiException::validation([]);
        }
        $existing = [];
        foreach ($this->items->byVehicle($vehicleId) as $item) {
            $existing[$item['id']] = $item;
        }
        $withRecords = $this->items->idsWithRecords($vehicleId);

        $errors = [];
        $clean = [];
        $names = [];
        foreach ($list as $i => $raw) {
            $key = "items.{$i}";
            if (!is_array($raw)) {
                $errors["{$key}.name"] = Msg::m10('品目名');
                continue;
            }
            $id = isset($raw['id']) && $raw['id'] !== null ? Validate::int($raw['id'], 1, PHP_INT_MAX) : null;
            if ($id !== null && !isset($existing[$id])) {
                throw ApiException::notFound();
            }

            $name = Validate::text($raw['name'] ?? '', 30);
            if ($name === null) {
                $errors["{$key}.name"] = Msg::m16('品目名', 30);
            } elseif ($name === '') {
                $errors["{$key}.name"] = Msg::m10('品目名');
            } elseif (isset($names[$name])) {
                $errors["{$key}.name"] = Msg::m21($name);
            } else {
                $names[$name] = true;
            }

            $kind = $raw['kind'] ?? null;
            if (!in_array($kind, ['replace', 'setting'], true)) {
                $errors["{$key}.kind"] = Msg::m10('種別');
            } elseif ($id !== null && isset($withRecords[$id]) && $existing[$id]['kind'] !== $kind) {
                $errors["{$key}.kind"] = Msg::M22;
            }

            $measure = null;
            $initial = null;
            if ($kind === 'replace') {
                $measure = $raw['measure'] ?? null;
                if (!in_array($measure, ['distance', 'period'], true)) {
                    $errors["{$key}.measure"] = Msg::m10('管理方法');
                }
            } elseif ($kind === 'setting') {
                $rawInitial = $raw['initial_value'] ?? null;
                $initial = Validate::int($rawInitial, -99, 99);
                if ($initial === null) {
                    $errors["{$key}.initial_value"] = ($rawInitial === null || $rawInitial === '')
                        ? Msg::m10('初期値') : Msg::m15('初期値');
                }
            }

            $clean[] = [
                'id' => $id,
                'name' => $name,
                'kind' => $kind,
                'measure' => $measure,
                'initial_value' => $initial,
                'sort_order' => $i + 1,
                'is_active' => !array_key_exists('is_active', $raw) || Validate::bool($raw['is_active']),
            ];
        }

        // 送られなかった既存の品目と名前が重ならないか
        $listedIds = array_filter(array_column($clean, 'id'));
        foreach ($existing as $id => $item) {
            if (!in_array($id, $listedIds, true) && isset($names[$item['name']])) {
                foreach ($clean as $i => $c) {
                    if ($c['name'] === $item['name']) {
                        $errors["items.{$i}.name"] = Msg::m21($item['name']);
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }

        Db::transaction(function () use ($vehicleId, $clean): void {
            foreach ($clean as $c) {
                if ($c['id'] !== null) {
                    $this->items->renameTemporarily($c['id']);
                }
            }
            foreach ($clean as $c) {
                if ($c['id'] === null) {
                    $this->items->insert($vehicleId, $c);
                } else {
                    $this->items->update($c['id'], $c);
                }
            }
        });
    }

    /** 品目を削除する。記録のある品目は削除できない（D04）。 */
    public function delete(int $itemId): void
    {
        $item = $this->items->find($itemId);
        if ($item === null) {
            throw ApiException::notFound();
        }
        if (isset($this->items->idsWithRecords($item['vehicle_id'])[$itemId])) {
            throw ApiException::conflict(Msg::ITEM_IN_USE);
        }
        $this->items->delete($itemId);
    }
}
