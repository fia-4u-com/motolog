<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Controller;

use Motolog\Http\Request;
use Motolog\Http\Response;
use Motolog\Http\Session;
use Motolog\Service\AuthService;
use Motolog\Service\VehicleService;
use Motolog\Support\Msg;
use Motolog\Support\View;

/** 画面（HTML）を返す処理（P01〜P07）。 */
final class PageController
{
    public function loginForm(Request $request): Response
    {
        if (Session::userId() !== null) {
            return Response::redirect('/');
        }
        $expired = isset($request->query['expired']);
        return self::loginPage($expired ? Msg::M04 : null, '');
    }

    public function login(Request $request): Response
    {
        $loginId = trim((string) ($request->post['login_id'] ?? ''));
        $password = (string) ($request->post['password'] ?? '');
        $result = (new AuthService())->attempt($loginId, $password, $request->ip);
        if (!$result['ok']) {
            return self::loginPage($result['message'], $loginId, 200);
        }
        Session::login($result['user_id']);
        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        Session::logout();
        return Response::redirect('/login');
    }

    public function main(Request $request): Response
    {
        if ((new VehicleService())->list(true) === []) {
            return Response::redirect('/vehicles/new');
        }
        return self::page('main', 'Motolog');
    }

    public function vehicleNew(Request $request): Response
    {
        $service = new VehicleService();
        return self::page('vehicle_edit', '車両の登録', [
            'vehicle' => null,
            'copySources' => $service->list(true),
        ]);
    }

    public function vehicleEdit(Request $request): Response
    {
        $vehicle = (new VehicleService())->get($request->intParam('id'));
        return self::page('vehicle_edit', '車両の編集', ['vehicle' => $vehicle, 'copySources' => []]);
    }

    public function items(Request $request): Response
    {
        $vehicle = (new VehicleService())->get($request->intParam('id'));
        return self::page('items', '品目の設定', ['vehicle' => $vehicle]);
    }

    /** @param array<string, mixed> $vars */
    private static function page(string $template, string $title, array $vars = []): Response
    {
        return Response::html(View::page($template, $vars + [
            'title' => $title,
            'page' => $template,
            'csrf' => Session::csrfToken(),
            'loggedIn' => true,
        ]));
    }

    private static function loginPage(?string $message, string $loginId, int $status = 200): Response
    {
        return Response::html(View::page('login', [
            'title' => 'ログイン',
            'page' => 'login',
            'csrf' => Session::csrfToken(),
            'loggedIn' => false,
            'message' => $message,
            'loginId' => $loginId,
        ]), $status);
    }
}
