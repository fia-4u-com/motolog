<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 共通の初期化：クラスの自動読み込み、設定の読み込み、文字コード・タイムゾーン。
 * 画面（public/index.php）とコマンド（tools/）の両方から読み込む。
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Motolog\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require APP_ROOT . '/src/Support/functions.php';

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');

Motolog\Support\Config::load(APP_ROOT . '/config/config.php');
