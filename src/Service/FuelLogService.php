<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

use Motolog\Http\ApiException;
use Motolog\Repository\FuelLogRepository;
use Motolog\Repository\VehicleRepository;
use Motolog\Support\Msg;
use Motolog\Support\Validate;

/** 給油記録（F-20〜F-23）。 */
final class FuelLogService
{
    public function __construct(
        private readonly FuelLogRepository $fuels = new FuelLogRepository(),
        private readonly VehicleRepository $vehicleRepo = new VehicleRepository(),
        private readonly VehicleService $vehicles = new VehicleService(),
    ) {
    }

    /** 一覧（A14）。 @return array<string, mixed> */
    public function list(int $vehicleId, mixed $page): array
    {
        $this->vehicles->mustFind($vehicleId);
        $calc = FuelCalculator::calculate($this->fuels->byVehicle($vehicleId), $this->startOdo($vehicleId));
        return Pager::slice($calc['rows'], $page);
    }

    /** 燃費の起点：その車両の最初の走行記録の ODO（なければ 0）。C-08／D06。 */
    public function startOdo(int $vehicleId): int
    {
        $first = $this->vehicleRepo->firstRideLog($vehicleId);
        return $first === null ? 0 : (int) $first['odo'];
    }

    /** 登録（A15）。 @param array<string, mixed> $input */
    public function create(int $vehicleId, array $input): int
    {
        $this->vehicles->mustFind($vehicleId);
        $v = $this->validate($vehicleId, $input, null);
        return $this->fuels->insert($vehicleId, $v['odo'], $v['centiliters'], $v['is_full']);
    }

    /** 修正（A16）。 @param array<string, mixed> $input */
    public function update(int $fuelId, array $input): void
    {
        $fuel = $this->mustFindFuel($fuelId);
        $v = $this->validate((int) $fuel['vehicle_id'], $input, $fuelId);
        $this->fuels->update($fuelId, $v['odo'], $v['centiliters'], $v['is_full']);
    }

    /** 削除（A17）。 */
    public function delete(int $fuelId): void
    {
        $this->mustFindFuel($fuelId);
        $this->fuels->delete($fuelId);
    }

    /** @return array<string, mixed> */
    private function mustFindFuel(int $fuelId): array
    {
        $fuel = $this->fuels->find($fuelId);
        if ($fuel === null) {
            throw ApiException::notFound();
        }
        return $fuel;
    }

    /**
     * 入力チェック（詳細設計書 4_入力チェック S02 給油）。
     *
     * @param array<string, mixed> $input
     * @return array{odo: int, centiliters: int, is_full: bool}
     */
    private function validate(int $vehicleId, array $input, ?int $fuelId): array
    {
        $errors = [];
        $odoRaw = $input['odo'] ?? '';
        $odo = Validate::int($odoRaw, 1, 9999999);
        if ($odo === null) {
            $errors['odo'] = ($odoRaw === '' || $odoRaw === null) ? Msg::m10('ODO') : Msg::m13('ODO', 1, '9,999,999');
        }
        $litersRaw = $input['liters'] ?? '';
        $centiliters = Validate::centiliters($litersRaw);
        if ($centiliters === null) {
            $errors['liters'] = ($litersRaw === '' || $litersRaw === null) ? Msg::m10('給油量') : Msg::M18;
        }
        if ($errors !== []) {
            throw ApiException::validation($errors);
        }
        if ($this->fuels->odoExists($vehicleId, $odo, $fuelId)) {
            throw ApiException::conflict(Msg::m17($odo));
        }
        return [
            'odo' => $odo,
            'centiliters' => $centiliters,
            'is_full' => !array_key_exists('is_full', $input) || Validate::bool($input['is_full']),
        ];
    }
}
