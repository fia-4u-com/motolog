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
use Motolog\Repository\RideLogRepository;
use Motolog\Support\Db;
use Motolog\Support\Msg;
use Motolog\Support\Validate;

/** 走行記録（F-10〜F-16／B-01／B-03）。 */
final class RideLogService
{
    public function __construct(
        private readonly RideLogRepository $logs = new RideLogRepository(),
        private readonly ItemRepository $items = new ItemRepository(),
        private readonly VehicleService $vehicles = new VehicleService(),
    ) {
    }

    /**
     * 一覧（A10。v1.1）。$year を省略すると「最新」（直近20件）、年を渡すとその年の記録すべて。
     * $focus に記録の ID を渡すと、その記録が直近20件に入っていれば「最新」、入っていなければその記録の年を返す（$year より優先）。
     *
     * @return array<string, mixed>
     */
    public function list(int $vehicleId, mixed $year, mixed $focus = null): array
    {
        $vehicle = $this->vehicles->mustFind($vehicleId);
        $items = $this->items->byVehicle($vehicleId, true);
        $rows = RideCalculator::rows($vehicle, $items, $this->logs->byVehicle($vehicleId));
        $years = Pager::years($rows);
        $view = (is_numeric($year) && in_array((int) $year, $years, true)) ? (int) $year : 'latest';
        if (is_numeric($focus)) {
            $latestIds = array_column(Pager::latest($rows), 'id');
            foreach ($rows as $row) {
                if ($row['id'] === (int) $focus) {
                    $view = in_array($row['id'], $latestIds, true) ? 'latest' : (int) substr($row['date'], 0, 4);
                }
            }
        }
        return [
            'items' => array_map(static fn (array $i): array => [
                'id' => $i['id'], 'name' => $i['name'], 'kind' => $i['kind'], 'measure' => $i['measure'],
            ], $items),
            'view' => $view,
            'years' => $years,
            'rows' => $view === 'latest' ? Pager::latest($rows) : Pager::byYear($rows, $view),
        ];
    }

    /** 登録（A11）。 @param array<string, mixed> $input */
    public function create(int $vehicleId, array $input): int
    {
        $vehicle = $this->vehicles->mustFind($vehicleId);
        $v = $this->validate($vehicle, $input, null);
        return Db::transaction(function () use ($vehicleId, $v): int {
            $id = $this->logs->insert($vehicleId, $v['date'], $v['odo'], $v['comment']);
            $this->logs->replaceItems($id, $v['replaced'], $v['deltas']);
            return $id;
        });
    }

    /** 修正（A12）。 @param array<string, mixed> $input */
    public function update(int $logId, array $input): void
    {
        $log = $this->mustFindLog($logId);
        $vehicle = $this->vehicles->mustFind((int) $log['vehicle_id']);
        $v = $this->validate($vehicle, $input, $logId);
        Db::transaction(function () use ($logId, $v): void {
            $this->logs->update($logId, $v['date'], $v['odo'], $v['comment']);
            $this->logs->replaceItems($logId, $v['replaced'], $v['deltas']);
        });
    }

    /** 削除（A13）。走行記録の品目も一緒に消える（外部キーの連鎖削除）。 */
    public function delete(int $logId): void
    {
        $this->mustFindLog($logId);
        $this->logs->delete($logId);
    }

    /** @return array<string, mixed> */
    private function mustFindLog(int $logId): array
    {
        $log = $this->logs->find($logId);
        if ($log === null) {
            throw ApiException::notFound();
        }
        return $log;
    }

    /**
     * 入力チェック（詳細設計書 4_入力チェック S02 走行）。
     *
     * @param array<string, mixed> $vehicle
     * @param array<string, mixed> $input
     * @return array{date: string, odo: int, comment: string, replaced: list<int>, deltas: array<int, int>}
     */
    private function validate(array $vehicle, array $input, ?int $logId): array
    {
        $vehicleId = (int) $vehicle['id'];
        $errors = [];

        $dateRaw = $input['date'] ?? '';
        $date = Validate::date($dateRaw);
        if ($date === null) {
            $errors['date'] = ($dateRaw === '' || $dateRaw === null) ? Msg::m10('日付') : Msg::m11('日付');
        } elseif ($date < (string) $vehicle['acquired_on']) {
            $errors['date'] = Msg::RIDE_BEFORE_ACQUIRED;
        }

        $odoRaw = $input['odo'] ?? '';
        $odo = Validate::int($odoRaw, 0, 9999999);
        if ($odo === null) {
            $errors['odo'] = ($odoRaw === '' || $odoRaw === null) ? Msg::m10('ODO') : Msg::m13('ODO', 0, '9,999,999');
        }

        $comment = Validate::text($input['comment'] ?? '', 255);
        if ($comment === null) {
            $errors['comment'] = Msg::m16('コメント', 255);
        }

        $items = [];
        foreach ($this->items->byVehicle($vehicleId) as $item) {
            $items[$item['id']] = $item;
        }

        $replaced = [];
        foreach ((array) ($input['replaced'] ?? []) as $raw) {
            $id = Validate::int($raw, 1, PHP_INT_MAX);
            if ($id === null || !isset($items[$id]) || $items[$id]['kind'] !== 'replace') {
                $errors['replaced'] = Msg::VALIDATION;
                continue;
            }
            $replaced[$id] = $id;
        }

        $deltas = [];
        foreach ((array) ($input['deltas'] ?? []) as $rawId => $rawDelta) {
            $id = Validate::int($rawId, 1, PHP_INT_MAX);
            if ($id === null || !isset($items[$id]) || $items[$id]['kind'] !== 'setting') {
                $errors['deltas'] = Msg::VALIDATION;
                continue;
            }
            if ($rawDelta === null || $rawDelta === '' || $rawDelta === 0 || $rawDelta === '0') {
                continue;
            }
            $delta = Validate::int($rawDelta, -99, 99);
            if ($delta === null) {
                $errors["deltas.{$id}"] = Msg::m15($items[$id]['name']);
            } elseif ($delta !== 0) {
                $deltas[$id] = $delta;
            }
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }

        if ($this->logs->dateExists($vehicleId, $date, $logId)) {
            throw ApiException::conflict(Msg::m12($date));
        }
        [$prev, $next] = $this->logs->neighbors($vehicleId, $date, $logId);
        if (($prev !== null && $odo < (int) $prev['odo']) || ($next !== null && $odo > (int) $next['odo'])) {
            throw ApiException::validation(['odo' => Msg::m14($prev, $next)]);
        }

        return [
            'date' => $date,
            'odo' => $odo,
            'comment' => $comment,
            'replaced' => array_values($replaced),
            'deltas' => $deltas,
        ];
    }
}
