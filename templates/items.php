<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * S04 品目の設定。使う変数：$vehicle
 */
?>
<section class="card">
  <h1>品目の設定：<?= h($vehicle['name']) ?></h1>
  <p class="message error" data-d="error" hidden></p>
  <div id="items-list" class="items-list" data-vehicle-id="<?= h($vehicle['id']) ?>"></div>
  <div class="form-buttons">
    <a href="/vehicles/<?= h($vehicle['id']) ?>/edit" class="btn">戻る</a>
    <button type="button" data-act="add">品目を追加</button>
    <button type="button" class="btn-primary" data-act="save">保存</button>
  </div>
</section>

<template id="tpl-item">
  <div class="item-card">
    <div class="item-move">
      <button type="button" data-act="up" aria-label="上へ">▲</button>
      <button type="button" data-act="down" aria-label="下へ">▼</button>
    </div>
    <div class="item-fields">
      <label>品目名 <input type="text" name="name" maxlength="30"></label>
      <p class="field-error" data-f="name"></p>
      <label>種別
        <select name="kind">
          <option value="replace">交換</option>
          <option value="setting">設定値</option>
        </select>
      </label>
      <p class="field-error" data-f="kind"></p>
      <label data-for="replace">管理方法
        <select name="measure">
          <option value="distance">距離</option>
          <option value="period">期間</option>
        </select>
      </label>
      <p class="field-error" data-f="measure"></p>
      <label data-for="setting">初期値 <input type="number" name="initial_value" step="1" min="-99" max="99"></label>
      <p class="field-error" data-f="initial_value"></p>
      <label class="check"><input type="checkbox" name="is_active"> 使用中</label>
    </div>
    <button type="button" class="btn-danger" data-act="delete">削除</button>
  </div>
</template>
