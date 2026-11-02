<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Support;

/**
 * テンプレート（templates/*.php）を HTML にする。
 * テンプレートには HTML の形だけを書き、値は h() を通して出す（N-26）。
 */
final class View
{
    /**
     * 画面の中身を作り、共通のレイアウト（layout.php）に入れて返す。
     *
     * @param array<string, mixed> $vars
     */
    public static function page(string $template, array $vars = []): string
    {
        $content = self::render($template, $vars);
        return self::render('layout', $vars + ['content' => $content]);
    }

    /** @param array<string, mixed> $vars */
    public static function render(string $template, array $vars = []): string
    {
        $file = APP_ROOT . '/templates/' . $template . '.php';
        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            require $__file;
            return (string) ob_get_clean();
        })($file, $vars);
    }
}
