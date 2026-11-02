<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Http;

/**
 * ログイン状態と CSRF の合言葉。
 * セッションの保存場所・名前・Cookie の属性は PHP-FPM の専用プールで設定する（N-06）。
 * ここでは「最後に使ってから14日」の期限（F-03）を管理する。
 */
final class Session
{
    public const LIFETIME = 14 * 24 * 60 * 60;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => self::LIFETIME,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $last = $_SESSION['last_access'] ?? null;
        if (is_int($last) && time() - $last > self::LIFETIME) {
            self::destroy();
            session_start();
        }
        if (self::userId() !== null) {
            $_SESSION['last_access'] = time();
            self::extendCookie();
        }
    }

    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    /** ログイン成功時：セッションIDを作り直してから記録する（N-16）。 */
    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => $userId,
            'last_access' => time(),
            'csrf' => bin2hex(random_bytes(32)),
        ];
        self::extendCookie();
    }

    public static function logout(): void
    {
        self::destroy();
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf']) && is_string($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    /** 使うたびに Cookie の期限を14日後に延ばす。 */
    private static function extendCookie(): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(session_name(), (string) session_id(), [
            'expires' => time() + self::LIFETIME,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function destroy(): void
    {
        $_SESSION = [];
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }
}
