<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog;

use Motolog\Controller\ApiController;
use Motolog\Controller\PageController;
use Motolog\Http\ApiException;
use Motolog\Http\Request;
use Motolog\Http\Response;
use Motolog\Http\Router;
use Motolog\Http\Session;
use Motolog\Support\Config;
use Motolog\Support\Logger;
use Motolog\Support\Msg;
use Throwable;

/** 画面と API の入口（詳細設計書 5_API一覧）。 */
final class App
{
    public static function run(): void
    {
        $request = Request::fromGlobals();
        try {
            Session::start();
            $response = self::router()->dispatch($request);
        } catch (ApiException $e) {
            $response = self::fromApiException($request, $e);
        } catch (Throwable $e) {
            Logger::error($request->method . ' ' . $request->path, $e);
            $message = Config::debug() ? Msg::M99 . ' (' . $e->getMessage() . ')' : Msg::M99;
            $response = $request->isApi()
                ? Response::error(new ApiException('SERVER_ERROR', 500, $message))
                : Response::html('<!doctype html><meta charset="utf-8"><title>Motolog</title><p>' . h($message) . '</p>', 500);
        }
        $response->send();
    }

    private static function fromApiException(Request $request, ApiException $e): Response
    {
        if ($request->isApi()) {
            return Response::error($e);
        }
        return match ($e->errorCode) {
            'UNAUTHENTICATED' => Response::redirect('/login'),
            'CSRF' => Response::redirect($request->path === '/login' ? '/login' : '/'),
            default => Response::html(
                '<!doctype html><meta charset="utf-8"><title>Motolog</title><p>' . h($e->getMessage()) . '</p><p><a href="/">戻る</a></p>',
                $e->status
            ),
        };
    }

    private static function router(): Router
    {
        $page = new PageController();
        $api = new ApiController();
        $r = new Router();

        // 画面
        $r->add('GET', '/login', [$page, 'loginForm'], false);
        $r->add('POST', '/login', [$page, 'login'], false);
        $r->add('POST', '/logout', [$page, 'logout']);
        $r->add('GET', '/', [$page, 'main']);
        $r->add('GET', '/vehicles/new', [$page, 'vehicleNew']);
        $r->add('GET', '/vehicles/{id}/edit', [$page, 'vehicleEdit']);
        $r->add('GET', '/vehicles/{id}/items', [$page, 'items']);

        // API
        $r->add('GET', '/api/vehicles', [$api, 'vehicles']);
        $r->add('POST', '/api/vehicles', [$api, 'createVehicle']);
        $r->add('PUT', '/api/vehicles/{id}', [$api, 'updateVehicle']);
        $r->add('POST', '/api/vehicles/{id}/image', [$api, 'uploadImage']);
        $r->add('GET', '/api/vehicles/{id}/image', [$api, 'image']);
        $r->add('GET', '/api/vehicles/{id}/items', [$api, 'items']);
        $r->add('PUT', '/api/vehicles/{id}/items', [$api, 'saveItems']);
        $r->add('DELETE', '/api/items/{id}', [$api, 'deleteItem']);
        $r->add('GET', '/api/vehicles/{id}/summary', [$api, 'summary']);
        $r->add('GET', '/api/vehicles/{id}/ride-logs', [$api, 'rideLogs']);
        $r->add('POST', '/api/vehicles/{id}/ride-logs', [$api, 'createRideLog']);
        $r->add('PUT', '/api/ride-logs/{id}', [$api, 'updateRideLog']);
        $r->add('DELETE', '/api/ride-logs/{id}', [$api, 'deleteRideLog']);
        $r->add('GET', '/api/vehicles/{id}/fuel-logs', [$api, 'fuelLogs']);
        $r->add('POST', '/api/vehicles/{id}/fuel-logs', [$api, 'createFuelLog']);
        $r->add('PUT', '/api/fuel-logs/{id}', [$api, 'updateFuelLog']);
        $r->add('DELETE', '/api/fuel-logs/{id}', [$api, 'deleteFuelLog']);

        return $r;
    }
}
