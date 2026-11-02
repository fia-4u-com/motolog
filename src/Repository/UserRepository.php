<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Repository;

use Motolog\Support\Db;

/** users テーブル。 */
final class UserRepository
{
    /** @return array<string, mixed>|null */
    public function findByLoginId(string $loginId): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, login_id, password_hash FROM users WHERE login_id = ?');
        $st->execute([$loginId]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return Db::pdo()->query('SELECT id, login_id FROM users ORDER BY id')->fetchAll();
    }

    public function insert(string $loginId, string $passwordHash): int
    {
        $st = Db::pdo()->prepare('INSERT INTO users (login_id, password_hash) VALUES (?, ?)');
        $st->execute([$loginId, $passwordHash]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $st = Db::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $st->execute([$passwordHash, $id]);
    }
}
