<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Support;

use PDO;
use Throwable;

/** DB 接続。SQL はリポジトリ層だけで使う。 */
final class Db
{
    private static ?PDO $pdo = null;

    /** アプリ用のユーザ（config の db.user）で接続する。 */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect((string) Config::get('db.user'), (string) Config::get('db.pass'));
        }
        return self::$pdo;
    }

    /** ユーザを指定して接続する（パスワード設定などのコマンドで管理用ユーザを使うとき）。 */
    public static function connect(string $user, string $password): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            (string) Config::get('db.host', 'localhost'),
            (string) Config::get('db.name', 'motolog')
        );
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** アプリ用の接続を差し替える（コマンドで管理用ユーザの接続をサービスに使わせるとき）。 */
    public static function use(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * 処理を1つのトランザクションで行う。例外が出たらすべて取り消す。
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
