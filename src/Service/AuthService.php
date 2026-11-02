<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

use Motolog\Repository\LoginAttemptRepository;
use Motolog\Repository\UserRepository;
use Motolog\Support\Msg;

/** ログイン（F-01／N-11／N-16／D07）。 */
final class AuthService
{
    /** 同じ接続元からの失敗がこの回数に達したら、ログインできなくする（D07）。 */
    public const MAX_FAILURES = 5;
    public const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly LoginAttemptRepository $attempts = new LoginAttemptRepository(),
    ) {
    }

    /**
     * 成功ならユーザID、失敗なら画面に出すメッセージを返す。
     *
     * @return array{ok: true, user_id: int}|array{ok: false, message: string}
     */
    public function attempt(string $loginId, string $password, string $ip): array
    {
        if ($loginId === '' || $password === '') {
            return ['ok' => false, 'message' => Msg::M01];
        }
        if ($this->attempts->recentFailures($ip, self::WINDOW_MINUTES) >= self::MAX_FAILURES) {
            return ['ok' => false, 'message' => Msg::M03];
        }

        $user = $this->users->findByLoginId($loginId);
        $hash = $user === null ? '' : (string) $user['password_hash'];
        $ok = $hash !== '' && password_verify($password, $hash);
        $this->attempts->record($ip, $loginId, $ok);

        if (!$ok) {
            return ['ok' => false, 'message' => Msg::M02];
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $this->attempts->purge();
        return ['ok' => true, 'user_id' => (int) $user['id']];
    }
}
