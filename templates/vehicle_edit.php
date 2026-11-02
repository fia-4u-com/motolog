<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * S03 車両の登録・編集。使う変数：$vehicle（新規のときは null）、$copySources（品目のコピー元の車両）
 */
$v = $vehicle ?? [];
$isNew = $vehicle === null;
?>
<section class="card">
  <h1><?= h($isNew ? '車両の登録' : '車両の編集') ?></h1>
  <p class="message error" data-d="error" hidden></p>
  <form id="vehicle-form" class="form-stack" novalidate data-vehicle-id="<?= h($v['id'] ?? '') ?>">
    <label>車両名 <input type="text" name="name" maxlength="50" value="<?= h($v['name'] ?? '') ?>"></label>
    <p class="field-error" data-field="name"></p>
    <label>車台番号 <input type="text" name="vin" maxlength="30" value="<?= h($v['vin'] ?? '') ?>"></label>
    <p class="field-error" data-field="vin"></p>
    <label>登録番号 <input type="text" name="plate" maxlength="30" value="<?= h($v['plate'] ?? '') ?>"></label>
    <p class="field-error" data-field="plate"></p>
    <label>取得日（新車は登録日、中古車は取得した日）
      <input type="date" name="acquired_on" value="<?= h($v['acquired_on'] ?? '') ?>">
    </label>
    <p class="field-error" data-field="acquired_on"></p>
    <label>初度登録年月（中古車のときだけ。車検証の値）
      <input type="month" name="first_registered_on" value="<?= h($v['first_registered_on'] ?? '') ?>">
    </label>
    <p class="field-error" data-field="first_registered_on"></p>
    <label>保有状態
      <select name="status">
        <option value="active" <?= ($v['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>保有中</option>
        <option value="retired" <?= ($v['status'] ?? '') === 'retired' ? 'selected' : '' ?>>保有終了</option>
      </select>
    </label>
    <?php if ($isNew && $copySources !== []): ?>
      <label>品目をコピーする車両
        <select name="copy_items_from">
          <option value="">コピーしない</option>
          <?php foreach ($copySources as $src): ?>
            <option value="<?= h($src['id']) ?>"><?= h($src['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>

    <fieldset class="image-field">
      <legend>車両画像（4:3 に切り取ります）</legend>
      <?php if (!$isNew && ($v['has_image'] ?? false)): ?>
        <img class="vehicle-image" src="/api/vehicles/<?= h($v['id']) ?>/image" alt="現在の車両画像" id="current-image">
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
      <p class="field-error" data-field="image"></p>
      <div class="crop" hidden>
        <canvas class="crop-canvas" width="800" height="600" aria-label="切り取る範囲（ドラッグで位置を調整）"></canvas>
        <label>拡大 <input type="range" class="crop-zoom" min="1" max="4" step="0.01" value="1"></label>
        <button type="button" data-act="crop-done">この範囲で決定</button>
      </div>
      <img class="vehicle-image crop-result" alt="切り取った画像" hidden>
    </fieldset>

    <div class="form-buttons">
      <a href="/" class="btn">戻る</a>
      <?php if (!$isNew): ?>
        <a href="/vehicles/<?= h($v['id']) ?>/items" class="btn">品目の設定へ</a>
      <?php endif; ?>
      <button type="submit" class="btn-primary">保存</button>
    </div>
  </form>
</section>
