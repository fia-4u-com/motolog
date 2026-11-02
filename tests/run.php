<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 単体テストの実行（外部ライブラリを使わない、簡単な自作の仕組み）。
 * tests/ の *Test.php を読み込み、test_ で始まる関数をすべて実行する。DB は使わない。
 *
 * 使い方：php tests/run.php
 * 終了コード：0＝すべて成功、1＝失敗あり
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

// 単体テストは DB を使わないので、設定ファイルがなくても動くようにする
define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Motolog\\')) {
        $path = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');

final class AssertionFailed extends Exception
{
}

function assertSame(mixed $expected, mixed $actual, string $label = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(sprintf(
            '%s 期待値 %s／実際 %s',
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrue(bool $condition, string $label = ''): void
{
    if (!$condition) {
        throw new AssertionFailed($label . ' が成り立ちません');
    }
}

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$passed = 0;
$failed = 0;
foreach (get_defined_functions()['user'] as $fn) {
    if (!str_starts_with($fn, 'test_')) {
        continue;
    }
    try {
        $fn();
        $passed++;
        echo "  ○ {$fn}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  × {$fn}：" . $e->getMessage() . "\n";
    }
}
echo "\n成功 {$passed} 件、失敗 {$failed} 件\n";
exit($failed === 0 ? 0 : 1);
