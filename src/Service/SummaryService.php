<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

use Motolog\Repository\FuelLogRepository;
use Motolog\Repository\ItemRepository;
use Motolog\Repository\RideLogRepository;

/** サマリと最終交換状況（A09。F-14／F-25）。 */
final class SummaryService
{
    public function __construct(
        private readonly VehicleService $vehicles = new VehicleService(),
        private readonly ItemRepository $items = new ItemRepository(),
        private readonly RideLogRepository $logs = new RideLogRepository(),
        private readonly FuelLogRepository $fuels = new FuelLogRepository(),
        private readonly FuelLogService $fuelService = new FuelLogService(),
    ) {
    }

    /** @return array<string, mixed> */
    public function get(int $vehicleId, ?string $today = null): array
    {
        $vehicle = $this->vehicles->mustFind($vehicleId);
        $logs = $this->logs->byVehicle($vehicleId);
        $fuels = $this->fuels->byVehicle($vehicleId);

        $odos = array_merge(array_column($logs, 'odo'), array_column($fuels, 'odo'));
        $currentOdo = $odos === [] ? null : max($odos);    // C-00
        $calc = FuelCalculator::calculate($fuels, $this->fuelService->startOdo($vehicleId));

        return [
            'vehicle' => $this->vehicles->get($vehicleId),
            'summary' => [
                'current_odo' => $currentOdo,
                'total_liters' => $fuels === [] ? null : $calc['total_liters'],
                'lifetime_economy' => $calc['lifetime_economy'],
                'max_economy' => $calc['max_economy'],
                'min_economy' => $calc['min_economy'],
            ],
            'status' => RideCalculator::status(
                $vehicle,
                $this->items->byVehicle($vehicleId, true),
                $logs,
                $currentOdo,
                $today ?? date('Y-m-d')
            ),
        ];
    }
}
