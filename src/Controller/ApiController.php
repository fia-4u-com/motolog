<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Controller;

use Motolog\Http\ApiException;
use Motolog\Http\Request;
use Motolog\Http\Response;
use Motolog\Service\FuelLogService;
use Motolog\Service\ItemService;
use Motolog\Service\RideLogService;
use Motolog\Service\SummaryService;
use Motolog\Service\VehicleService;
use Motolog\Support\Validate;

/**
 * API（A01〜A17）の受付。入力をサービスに渡し、結果を JSON で返すだけにする。
 * 計算と業務ルールはサービス層に書く（N-26）。
 */
final class ApiController
{
    // ---- 車両 ----

    public function vehicles(Request $r): Response
    {
        return Response::ok((new VehicleService())->list(Validate::bool($r->query['include_retired'] ?? false)));
    }

    public function createVehicle(Request $r): Response
    {
        $id = (new VehicleService())->create($r->json());
        return Response::ok(['id' => $id], 201);
    }

    public function updateVehicle(Request $r): Response
    {
        (new VehicleService())->update($r->intParam('id'), $r->json());
        return Response::ok();
    }

    public function uploadImage(Request $r): Response
    {
        $file = $r->files['image'] ?? null;
        (new VehicleService())->saveImage($r->intParam('id'), is_array($file) ? $file : null);
        return Response::ok();
    }

    public function image(Request $r): Response
    {
        $path = (new VehicleService())->imagePath($r->intParam('id'));
        if ($path === null) {
            throw ApiException::notFound();
        }
        return Response::file($path, 'image/jpeg');
    }

    // ---- 品目 ----

    public function items(Request $r): Response
    {
        return Response::ok((new ItemService())->list($r->intParam('id')));
    }

    public function saveItems(Request $r): Response
    {
        $body = $r->json();
        (new ItemService())->saveAll($r->intParam('id'), $body['items'] ?? null);
        return Response::ok();
    }

    public function deleteItem(Request $r): Response
    {
        (new ItemService())->delete($r->intParam('id'));
        return Response::ok();
    }

    // ---- サマリ ----

    public function summary(Request $r): Response
    {
        return Response::ok((new SummaryService())->get($r->intParam('id')));
    }

    // ---- 走行記録 ----

    public function rideLogs(Request $r): Response
    {
        return Response::ok((new RideLogService())->list($r->intParam('id'), $r->query['year'] ?? null, $r->query['focus'] ?? null));
    }

    public function createRideLog(Request $r): Response
    {
        $id = (new RideLogService())->create($r->intParam('id'), $r->json());
        return Response::ok(['id' => $id], 201);
    }

    public function updateRideLog(Request $r): Response
    {
        (new RideLogService())->update($r->intParam('id'), $r->json());
        return Response::ok();
    }

    public function deleteRideLog(Request $r): Response
    {
        (new RideLogService())->delete($r->intParam('id'));
        return Response::ok();
    }

    // ---- 給油記録 ----

    public function fuelLogs(Request $r): Response
    {
        return Response::ok((new FuelLogService())->list($r->intParam('id'), $r->query['page'] ?? null));
    }

    public function createFuelLog(Request $r): Response
    {
        $id = (new FuelLogService())->create($r->intParam('id'), $r->json());
        return Response::ok(['id' => $id], 201);
    }

    public function updateFuelLog(Request $r): Response
    {
        (new FuelLogService())->update($r->intParam('id'), $r->json());
        return Response::ok();
    }

    public function deleteFuelLog(Request $r): Response
    {
        (new FuelLogService())->delete($r->intParam('id'));
        return Response::ok();
    }
}
