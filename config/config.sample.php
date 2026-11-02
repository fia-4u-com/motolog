<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 設定の見本。サーバでは、これを config.php という名前でコピーして値を入れる。
 * config.php は git に入れない（.gitignore で除外。N-10）。
 * パスワードは、リリース時に作り直した値をサーバ上で直接書く（サーバ作業手順書 P10-2）。
 */
declare(strict_types=1);

return [
    // true にすると、エラーの詳細を画面にも出す（テストのときだけ。本番は必ず false。N-13）
    'debug' => false,

    // アプリ用の DB 接続（SELECT・INSERT・UPDATE・DELETE だけの権限）
    'db' => [
        'host' => 'localhost',
        'name' => 'motolog',
        'user' => 'motolog',
        'pass' => '',
    ],

    // 管理用の DB ユーザ（パスワード設定のコマンドだけが使う）
    'admin_db' => [
        'user' => 'motolog_admin',
        'pass' => '',
    ],
];
