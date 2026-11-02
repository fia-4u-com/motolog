<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * S02 メイン。表の行・札などの1件分の形は <template> に用意し、
 * main.js はそれを複製して値をはめるだけにする（N-26）。
 */
?>
<section class="vehicle-bar">
  <select id="vehicle-select" aria-label="車両"></select>
</section>

<section id="summary" class="summary">
  <button type="button" class="summary-toggle mobile-only" aria-expanded="false">
    <span data-s="name"></span>
    <span data-s="current_odo"></span>
    <span data-s="lifetime_economy"></span>
    <span class="caret" aria-hidden="true">▼</span>
  </button>
  <div class="summary-body">
    <img id="vehicle-image" class="vehicle-image" alt="車両の画像" hidden>
    <dl class="summary-list">
      <dt>車両名</dt><dd data-s="name"></dd>
      <dt>車台番号</dt><dd data-s="vin"></dd>
      <dt>登録番号</dt><dd data-s="plate"></dd>
      <dt>総走行距離</dt><dd data-s="current_odo"></dd>
      <dt>総給油量</dt><dd data-s="total_liters"></dd>
      <dt>生涯燃費</dt><dd data-s="lifetime_economy"></dd>
      <dt>最高燃費</dt><dd data-s="max_economy"></dd>
      <dt>最低燃費</dt><dd data-s="min_economy"></dd>
    </dl>
  </div>
</section>

<nav class="tabs" role="tablist">
  <button type="button" role="tab" data-tab="ride" aria-selected="true">走行記録</button>
  <button type="button" role="tab" data-tab="fuel" aria-selected="false">給油記録</button>
</nav>

<section id="panel-ride" class="panel">
  <div id="status" class="status-strip" aria-label="最終交換状況"></div>
  <div class="table-wrap">
    <table class="log-table">
      <thead><tr id="ride-head"></tr></thead>
      <tbody id="ride-body"></tbody>
    </table>
  </div>
  <nav class="pager" id="ride-pager" aria-label="ページ送り"></nav>
  <button type="button" class="btn-primary add-button" id="ride-add">＋ 記録を追加</button>
</section>

<section id="panel-fuel" class="panel" hidden>
  <div class="table-wrap">
    <table class="log-table">
      <thead>
        <tr>
          <th class="sticky">ODO</th><th>給油量</th><th>満タン</th><th>差分</th><th>燃費</th>
        </tr>
      </thead>
      <tbody id="fuel-body"></tbody>
    </table>
  </div>
  <nav class="pager" id="fuel-pager" aria-label="ページ送り"></nav>
  <button type="button" class="btn-primary add-button" id="fuel-add">＋ 給油を追加</button>
</section>

<!-- 走行記録の入力・修正 -->
<dialog id="ride-dialog" class="dialog">
  <form id="ride-form" class="form-stack" novalidate>
    <h2 data-d="title">走行記録</h2>
    <p class="message error" data-d="error" hidden></p>
    <label>日付 <input type="date" name="date"></label>
    <p class="field-error" data-field="date"></p>
    <label>ODO（km） <input type="number" name="odo" min="0" step="1" inputmode="numeric"></label>
    <p class="field-error" data-field="odo"></p>
    <fieldset data-d="replace">
      <legend>交換した品目</legend>
      <div class="check-list"></div>
      <p class="field-error" data-field="replaced"></p>
    </fieldset>
    <fieldset data-d="setting">
      <legend>設定値の変更（増減。変えていなければ空）</legend>
      <div class="delta-list"></div>
      <p class="field-error" data-field="deltas"></p>
    </fieldset>
    <label>コメント <input type="text" name="comment" maxlength="255"></label>
    <p class="field-error" data-field="comment"></p>
    <div class="dialog-buttons">
      <button type="button" class="btn-danger" data-act="delete">削除</button>
      <span class="spacer"></span>
      <button type="button" data-act="cancel">取消</button>
      <button type="submit" class="btn-primary">保存</button>
    </div>
  </form>
</dialog>

<!-- 給油記録の入力・修正 -->
<dialog id="fuel-dialog" class="dialog">
  <form id="fuel-form" class="form-stack" novalidate>
    <h2 data-d="title">給油記録</h2>
    <p class="message error" data-d="error" hidden></p>
    <label>ODO（km） <input type="number" name="odo" min="1" step="1" inputmode="numeric"></label>
    <p class="field-error" data-field="odo"></p>
    <label>給油量（L。小数点なしでよい：5.50 L は 550）
      <input type="text" name="liters" inputmode="decimal" autocomplete="off" maxlength="5" placeholder="550">
    </label>
    <p class="liters-preview" data-d="liters-preview" aria-live="polite"></p>
    <p class="field-error" data-field="liters"></p>
    <label class="check"><input type="checkbox" name="is_full" checked> 満タン</label>
    <div class="dialog-buttons">
      <button type="button" class="btn-danger" data-act="delete">削除</button>
      <span class="spacer"></span>
      <button type="button" data-act="cancel">取消</button>
      <button type="submit" class="btn-primary">保存</button>
    </div>
  </form>
</dialog>

<!-- 1件分のひな形 -->
<template id="tpl-ride-row">
  <tr class="clickable">
    <td class="c-date sticky"></td>
    <td class="c-odo num"></td>
    <td class="c-elapsed"></td>
    <td class="c-diff num"></td>
    <td class="c-events"></td>
    <td class="c-comment"></td>
  </tr>
</template>
<template id="tpl-fuel-row">
  <tr class="clickable">
    <td class="c-odo num sticky"></td>
    <td class="c-liters num"></td>
    <td class="c-full"></td>
    <td class="c-diff num"></td>
    <td class="c-economy num"></td>
  </tr>
</template>
<template id="tpl-chip">
  <button type="button" class="chip">
    <span class="chip-name"></span>
    <span class="chip-date"></span>
    <span class="chip-value"></span>
  </button>
</template>
<template id="tpl-check">
  <label class="check"><input type="checkbox"> <span></span></label>
</template>
<template id="tpl-delta">
  <label class="delta"><span></span> <input type="number" step="1" min="-99" max="99" inputmode="numeric" placeholder="±0"></label>
</template>
