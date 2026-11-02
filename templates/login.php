<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * S01 ログイン。使う変数：$csrf, $message, $loginId
 */
?>
<section class="login-box">
  <h1 class="login-title">Motolog</h1>
  <?php if ($message !== null): ?>
    <p class="message error"><?= h($message) ?></p>
  <?php endif; ?>
  <form method="post" action="/login" class="form-stack">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <label>ID
      <input type="text" name="login_id" value="<?= h($loginId) ?>" autocomplete="username" autocapitalize="off" required>
    </label>
    <label>パスワード
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn-primary">ログイン</button>
  </form>
</section>
