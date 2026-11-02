<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * コマンド（tools/*.php）の共通部分。サーバで fia ユーザが実行する。
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Motolog\Support\Config;
use Motolog\Support\Db;

function out(string $message = ''): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, 'エラー：' . $message . PHP_EOL);
    exit(1);
}

/** 管理用ユーザで接続し、サービス・リポジトリもこの接続を使うようにする。 */
function connectAdmin(): PDO
{
    $user = (string) Config::get('admin_db.user', '');
    $pass = (string) Config::get('admin_db.pass', '');
    if ($user === '' || $pass === '') {
        fail('config/config.php の admin_db（管理用ユーザ）が設定されていません。');
    }
    try {
        $pdo = Db::connect($user, $pass);
    } catch (PDOException $e) {
        fail('DB に接続できませんでした（' . $e->getMessage() . '）');
    }
    Db::use($pdo);
    return $pdo;
}
