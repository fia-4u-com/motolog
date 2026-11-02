<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 入口。ブラウザからの要求はすべてここが受け取る（nginx の設定で、実在しないパスはここへ回す）。
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

Motolog\App::run();
