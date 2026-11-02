<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * テンプレートから使う関数。
 */
declare(strict_types=1);

/** HTML エスケープ。テンプレートで値を出すときは必ずこれを通す。 */
function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
