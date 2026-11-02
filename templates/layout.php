<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 全画面共通のレイアウト。値は h() を通して出す。
 * 使う変数：$title, $page, $csrf, $loggedIn, $content
 */
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= h($csrf) ?>">
  <meta name="theme-color" content="#1f4e78">
  <title><?= h($title) ?></title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="/assets/img/icon-192.png" type="image/png">
  <link rel="apple-touch-icon" href="/assets/img/icon-192.png">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="page-<?= h($page) ?>">
  <header class="app-header">
    <a class="brand" href="/">Motolog</a>
    <?php if ($loggedIn): ?>
      <form method="post" action="/logout" class="logout">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="btn-link">ログアウト</button>
      </form>
    <?php endif; ?>
  </header>
  <main class="app-main">
    <?= $content /* 中身のテンプレートで、すでにエスケープ済みの HTML */ ?>
  </main>
  <div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
  <?php if ($page !== 'login'): ?>
    <script type="module" src="/assets/js/<?= h($page) ?>.js"></script>
  <?php endif; ?>
</body>
</html>
