<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 入力値の形のチェック（詳細設計書 4_入力チェック）の単体テスト。
 */
declare(strict_types=1);

use Motolog\Support\Validate;

function test_validate_date(): void
{
    assertSame('2026-11-01', Validate::date('2026-11-01'));
    assertSame(null, Validate::date('2026-02-30'), '実在しない日');
    assertSame(null, Validate::date('2026/11/01'));
    assertSame(null, Validate::date(''));
    assertSame(null, Validate::date(null));
    assertSame('2020-04-01', Validate::month('2020-04'), '年月は月の1日にする');
    assertSame(null, Validate::month('2020-13'));
}

function test_validate_int(): void
{
    assertSame(7052, Validate::int('7052', 0, 9999999));
    assertSame(2, Validate::int('+2', -99, 99), '+ を付けてもよい');
    assertSame(-1, Validate::int('-1', -99, 99));
    assertSame(null, Validate::int('100', -99, 99), '範囲外');
    assertSame(null, Validate::int('1.5', 0, 10), '小数は不可');
    assertSame(null, Validate::int('abc', 0, 10));
    assertSame(5, Validate::int(5, 0, 10));
}

function test_validate_centiliters(): void
{
    assertSame(850, Validate::centiliters('8.5'));
    assertSame(850, Validate::centiliters('8.50'));
    assertSame(1000, Validate::centiliters('10'));
    assertSame(1, Validate::centiliters('0.01'));
    assertSame(9999, Validate::centiliters('99.99'));
    assertSame(null, Validate::centiliters('0'), '0 は不可');
    assertSame(null, Validate::centiliters('100'), '範囲外');
    assertSame(null, Validate::centiliters('8.505'), '小数第3位は不可');
    assertSame(1025, Validate::centiliters(10.25), '数値でもよい');
}

function test_validate_text(): void
{
    assertSame('abc', Validate::text('  abc  ', 10), '前後の空白を除く');
    assertSame('', Validate::text(null, 10));
    assertSame(null, Validate::text(str_repeat('あ', 256), 255), '長すぎ');
    assertSame(str_repeat('あ', 255), Validate::text(str_repeat('あ', 255), 255), '日本語も1文字と数える');
}
