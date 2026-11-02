<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Repository;

use Motolog\Support\Db;

/** login_attempts テーブル（ログインの試行回数制限。N-16／D07）。 */
final class LoginAttemptRepository
{
    public function recentFailures(string $ip, int $minutes): int
    {
        $st = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = ? AND succeeded = 0 AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
        );
        $st->execute([$ip, $minutes]);
        return (int) $st->fetchColumn();
    }

    public function record(string $ip, string $loginId, bool $succeeded): void
    {
        $st = Db::pdo()->prepare(
            'INSERT INTO login_attempts (ip_address, login_id, succeeded, attempted_at) VALUES (?, ?, ?, NOW())'
        );
        $st->execute([$ip, mb_substr($loginId, 0, 50), $succeeded ? 1 : 0]);
    }

    /** 30日より古い記録を消す。 */
    public function purge(): void
    {
        Db::pdo()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)');
    }
}
