/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * S02 メイン：車両の切替、サマリ、最終交換状況、走行記録・給油記録の一覧と登録・修正・削除。
 * 1件分の HTML は templates/main.php の <template> を複製して値をはめる（N-26）。
 * 値は textContent で入れ、innerHTML は使わない。
 */
import { api, ApiError, toast, showErrors, clearErrors } from './api.js';
import * as fmt from './format.js';

const STORAGE_KEY = 'motolog.vehicle';
const MSG_SAVED = '保存しました。';
const MSG_DELETED = '削除しました。';
const MSG_DISCARD = '入力中の内容は保存されていません。破棄してよろしいですか？';
const MSG_LITERS = '給油量は、小数点なし（5.50 L なら 550）か小数点付き（5.5）の数字で、0.01〜99.99 L の範囲で入力してください。';
const MSG_SMALL_LITERS = (l) => `給油量 ${l} L で保存します。1 L 未満ですが、よろしいですか？（5.00 L なら 500 と入力します）`;

const state = {
  vehicles: [],
  vehicleId: null,
  showRetired: false,
  tab: 'ride',
  rideView: 'latest',   // 走行記録：'latest'（直近20件）か、年（例：2025）
  fuelPage: null,
  ride: null,
  fuel: null,
};

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

function clone(templateId) {
  return document.getElementById(templateId).content.firstElementChild.cloneNode(true);
}

function element(tag, className, text) {
  const el = document.createElement(tag);
  if (className) el.className = className;
  if (text !== undefined) el.textContent = text;
  return el;
}

function handleError(e) {
  if (e instanceof ApiError) {
    toast(e.message);
  } else {
    console.error(e);
    toast('エラーが発生しました。時間をおいて、もう一度お試しください。');
  }
}

/* ---------------- 車両 ---------------- */

async function loadVehicles() {
  const query = state.showRetired ? '?include_retired=1' : '';
  state.vehicles = await api('GET', `/api/vehicles${query}`);
  const saved = Number(localStorage.getItem(STORAGE_KEY));
  const ids = state.vehicles.map((v) => v.id);
  if (!ids.includes(state.vehicleId)) {
    state.vehicleId = ids.includes(saved) ? saved : (ids[0] ?? null);
  }
  renderVehicleSelect();
}

function renderVehicleSelect() {
  const select = $('#vehicle-select');
  select.replaceChildren();
  for (const v of state.vehicles) {
    const label = v.status === 'retired' ? `${v.name}（保有終了）` : v.name;
    select.append(new Option(label, String(v.id), false, v.id === state.vehicleId));
  }
  const actions = document.createElement('optgroup');
  actions.label = '操作';
  actions.append(
    new Option('＋ 車両を追加', 'action:new'),
    new Option('この車両を編集', 'action:edit'),
    new Option('品目の設定', 'action:items'),
    new Option(state.showRetired ? '保有終了の車両を隠す' : '保有終了の車両を表示', 'action:retired'),
  );
  select.append(actions);
}

async function onVehicleChange(event) {
  const value = event.target.value;
  if (value.startsWith('action:')) {
    event.target.value = String(state.vehicleId);
    switch (value) {
      case 'action:new': location.href = '/vehicles/new'; return;
      case 'action:edit': location.href = `/vehicles/${state.vehicleId}/edit`; return;
      case 'action:items': location.href = `/vehicles/${state.vehicleId}/items`; return;
      case 'action:retired':
        state.showRetired = !state.showRetired;
        await loadVehicles();
        return;
      default: return;
    }
  }
  state.vehicleId = Number(value);
  localStorage.setItem(STORAGE_KEY, value);
  state.rideView = 'latest';
  state.fuelPage = null;
  state.ride = null;
  state.fuel = null;
  await refresh();
}

/* ---------------- サマリと最終交換状況 ---------------- */

async function loadSummary() {
  const data = await api('GET', `/api/vehicles/${state.vehicleId}/summary`);
  const v = data.vehicle;
  const s = data.summary;
  const values = {
    name: v.name,
    vin: v.vin || fmt.DASH,
    plate: v.plate || fmt.DASH,
    current_odo: fmt.km(s.current_odo),
    total_liters: fmt.liters(s.total_liters),
    lifetime_economy: fmt.economy(s.lifetime_economy),
    max_economy: fmt.economy(s.max_economy),
    min_economy: fmt.economy(s.min_economy),
  };
  $$('[data-s]').forEach((el) => { el.textContent = values[el.dataset.s] ?? ''; });

  const img = $('#vehicle-image');
  if (v.has_image) {
    img.src = `/api/vehicles/${v.id}/image?t=${Date.now()}`;
    img.hidden = false;
  } else {
    img.removeAttribute('src');
    img.hidden = true;
  }
  renderStatus(data.status);
}

function renderStatus(status) {
  const box = $('#status');
  box.replaceChildren();
  for (const s of status) {
    const chip = clone('tpl-chip');
    $('.chip-name', chip).textContent = s.name;
    if (s.kind === 'setting') {
      // 今の値と、車両に設定した初期値を並べる（v1.1）
      $('.chip-date', chip).textContent = `現在 ${s.value}`;
      $('.chip-value', chip).textContent = `初期値 ${s.initial}`;
    } else {
      $('.chip-date', chip).textContent = s.unreplaced ? '未交換' : s.last_date;
      $('.chip-value', chip).textContent = s.measure === 'period' ? fmt.elapsed(s.period) : fmt.km(s.distance);
      chip.classList.toggle('unreplaced', !!s.unreplaced);
    }
    // 押すと、最後に交換した（設定値は最後に変えた）記録へ移る。記録がなければ押せない
    if (s.last_log_id) {
      chip.title = s.kind === 'setting' ? '最後に変えた記録へ移る' : '最後に交換した記録へ移る';
      chip.addEventListener('click', () => { jumpToRide(s.last_log_id).catch(handleError); });
    } else {
      chip.disabled = true;
    }
    box.append(chip);
  }
}

/* ---------------- ページ送り ---------------- */

/** |<  <  （真ん中）  >  >| の5つを並べる。押せない向きのボタンは薄くする（v1.1） */
function pagerButtons(nav, middle, { first, prev, next, last }, labels) {
  const button = (text, title, target) => {
    const b = element('button', '', text);
    b.type = 'button';
    b.title = title;
    b.setAttribute('aria-label', title);
    b.disabled = target === null;
    if (target !== null) b.addEventListener('click', () => target());
    return b;
  };
  nav.replaceChildren(
    button('|<', labels[0], first), button('<', labels[1], prev), middle,
    button('>', labels[2], next), button('>|', labels[3], last),
  );
}

/**
 * 走行記録：「一番古い年 … 一番新しい年、最新」の順に並べ、そこを1つずつ動く。真ん中は年のプルダウン（最新・新しい年から順）。
 * 記録のない年は並べない。
 */
function renderRidePager(nav, years, view, go) {
  if (years.length === 0) { nav.replaceChildren(); return; }
  const seq = [...years, 'latest'];
  const i = seq.indexOf(view);
  const select = document.createElement('select');
  select.setAttribute('aria-label', '表示する年');
  select.append(new Option('最新', 'latest', false, view === 'latest'));
  for (const y of [...years].reverse()) select.append(new Option(`${y}年`, String(y), false, view === y));
  select.addEventListener('change', () => go(select.value === 'latest' ? 'latest' : Number(select.value)));
  pagerButtons(nav, select, {
    first: i > 0 ? () => go(seq[0]) : null,
    prev: i > 0 ? () => go(seq[i - 1]) : null,
    next: i < seq.length - 1 ? () => go(seq[i + 1]) : null,
    last: i < seq.length - 1 ? () => go('latest') : null,
  }, ['一番古い記録', '1年前', '1年新しい側へ', '最新']);
}

/** 給油記録：新しい側から20件ずつ。真ん中は「36〜55件目／55件」。1ページに収まるときは出さない */
function renderFuelPager(nav, page, go) {
  if (page.last <= 1) { nav.replaceChildren(); return; }
  const middle = element('span', 'pager-range', `${page.from}〜${page.to}件目／${page.total}件`);
  pagerButtons(nav, middle, {
    first: page.current > 1 ? () => go(1) : null,
    prev: page.current > 1 ? () => go(page.current - 1) : null,
    next: page.current < page.last ? () => go(page.current + 1) : null,
    last: page.current < page.last ? () => go(page.last) : null,
  }, ['一番古い記録', '20件古い側へ', '20件新しい側へ', '最新']);
}

/* ---------------- 走行記録 ---------------- */

async function loadRide() {
  const query = state.rideView === 'latest' ? '' : `?year=${state.rideView}`;
  state.ride = await api('GET', `/api/vehicles/${state.vehicleId}/ride-logs${query}`);
  state.rideView = state.ride.view;
  renderRide();
}

function renderRide() {
  const { rows, years, view } = state.ride;

  // 品目ごとの値は上の最終交換状況に出ているので、一覧には「その回にした整備」だけを出す（C-03〜C-05）
  const head = $('#ride-head');
  head.replaceChildren(
    element('th', 'sticky', '日付'), element('th', '', 'ODO'), element('th', '', '経過'), element('th', '', '差分'),
    element('th', '', '整備内容'), element('th', '', 'コメント'),
  );

  const body = $('#ride-body');
  body.replaceChildren();
  for (const row of rows) {
    const tr = clone('tpl-ride-row');
    tr.dataset.id = String(row.id);
    $('.c-date', tr).textContent = row.date;
    $('.c-odo', tr).textContent = fmt.km(row.odo);
    $('.c-elapsed', tr).textContent = fmt.elapsed(row.elapsed);
    $('.c-diff', tr).textContent = row.diff === null ? fmt.DASH : fmt.km(row.diff);
    $('.c-events', tr).textContent = row.events.map(fmt.event).join('、');
    $('.c-comment', tr).textContent = row.comment;
    tr.addEventListener('click', () => openRideDialog(row));
    body.append(tr);
  }

  renderRidePager($('#ride-pager'), years, view, (v) => { state.rideView = v; loadRide().catch(handleError); });
}

/** 最終交換状況の札から、その記録の行へ移る。別のページにあれば、そのページを開く */
async function jumpToRide(logId) {
  if (!$(`#ride-body tr[data-id="${logId}"]`)) {
    state.ride = await api('GET', `/api/vehicles/${state.vehicleId}/ride-logs?focus=${logId}`);
    state.rideView = state.ride.view;
    renderRide();
  }
  const tr = $(`#ride-body tr[data-id="${logId}"]`);
  if (!tr) return;
  tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
  tr.classList.remove('flash');
  void tr.offsetWidth;   // 続けて押したときも、色付けを最初からやり直す
  tr.classList.add('flash');
}

function collectRide(root) {
  const value = (name) => (root.querySelector(`[name="${name}"]`) || {}).value ?? '';
  const replaced = $$('[name="replace"]:checked', root).map((el) => Number(el.dataset.item));
  const deltas = {};
  for (const el of $$('[name="delta"]', root)) {
    if (el.value !== '' && Number(el.value) !== 0) deltas[el.dataset.item] = el.value;
  }
  return { date: value('date'), odo: value('odo'), comment: value('comment'), replaced, deltas };
}

/** 追加も修正も、PC・スマホ共通の入力フォームで行う（製作報告書 B01） */
function openRideDialog(row) {
  const dialog = $('#ride-dialog');
  const form = $('#ride-form');
  form.reset();
  clearErrors(form);
  form.dataset.id = row ? String(row.id) : '';
  $('[data-d="title"]', form).textContent = row ? '走行記録の修正' : '走行記録の追加';
  form.elements.date.value = row ? row.date : fmt.today();
  form.elements.odo.value = row ? row.odo : '';
  form.elements.comment.value = row ? row.comment : '';

  const items = state.ride.items;
  const checks = $('.check-list', form);
  const deltas = $('.delta-list', form);
  checks.replaceChildren();
  deltas.replaceChildren();
  for (const item of items) {
    if (item.kind === 'replace') {
      const label = clone('tpl-check');
      const cb = $('input', label);
      cb.name = 'replace';
      cb.dataset.item = item.id;
      cb.checked = !!row && row.replaced.includes(item.id);
      $('span', label).textContent = item.name;
      checks.append(label);
    } else {
      const label = clone('tpl-delta');
      const n = $('input', label);
      n.name = 'delta';
      n.dataset.item = item.id;
      n.value = row && row.deltas[item.id] ? row.deltas[item.id] : '';
      $('span', label).textContent = item.name;
      deltas.append(label);
    }
  }
  $('[data-d="replace"]', form).hidden = !items.some((i) => i.kind === 'replace');
  $('[data-d="setting"]', form).hidden = !items.some((i) => i.kind === 'setting');
  $('[data-act="delete"]', form).hidden = !row;
  form.dataset.dirty = '';
  dialog.showModal();
}

async function submitRide(event) {
  event.preventDefault();
  const form = event.target;
  const id = form.dataset.id;
  try {
    if (id) {
      await api('PUT', `/api/ride-logs/${id}`, collectRide(form));
    } else {
      await api('POST', `/api/vehicles/${state.vehicleId}/ride-logs`, collectRide(form));
      state.rideView = 'latest';
    }
    $('#ride-dialog').close();
    toast(MSG_SAVED);
    await Promise.all([loadSummary(), loadRide()]);
  } catch (e) {
    if (e instanceof ApiError) showErrors(form, e); else handleError(e);
  }
}

async function deleteRide() {
  const form = $('#ride-form');
  const row = state.ride.rows.find((r) => String(r.id) === form.dataset.id);
  if (!row) return;
  const message = `この走行・メンテナンス記録を削除します。よろしいですか？（${row.date}　ODO ${fmt.km(row.odo)}）`;
  if (!confirm(message)) return;
  try {
    await api('DELETE', `/api/ride-logs/${row.id}`);
    $('#ride-dialog').close();
    toast(MSG_DELETED);
    await Promise.all([loadSummary(), loadRide()]);
  } catch (e) {
    if (e instanceof ApiError) showErrors(form, e); else handleError(e);
  }
}

/* ---------------- 給油記録 ---------------- */

async function loadFuel() {
  const query = state.fuelPage ? `?page=${state.fuelPage}` : '';
  state.fuel = await api('GET', `/api/vehicles/${state.vehicleId}/fuel-logs${query}`);
  state.fuelPage = state.fuel.page.current;
  renderFuel();
}

function renderFuel() {
  const body = $('#fuel-body');
  body.replaceChildren();
  for (const row of state.fuel.rows) {
    const tr = clone('tpl-fuel-row');
    $('.c-odo', tr).textContent = fmt.km(row.odo);
    $('.c-liters', tr).textContent = fmt.liters(row.liters);
    $('.c-full', tr).textContent = row.is_full ? '✓' : '継ぎ足し';
    $('.c-diff', tr).textContent = fmt.km(row.diff);
    $('.c-economy', tr).textContent = fmt.economy(row.economy);
    tr.addEventListener('click', () => openFuelDialog(row));
    body.append(tr);
  }
  renderFuelPager($('#fuel-pager'), state.fuel.page, (p) => { state.fuelPage = p; loadFuel().catch(handleError); });
}

function collectFuel(root) {
  const raw = root.querySelector('[name="liters"]').value;
  const cl = fmt.parseLiters(raw);
  return {
    odo: root.querySelector('[name="odo"]').value,
    // 読めた値は "5.50" の形にして送る。読めない値はそのまま送り、サーバの入力チェック（M18）に任せる
    liters: cl === null ? raw : fmt.centilitersText(cl),
    is_full: root.querySelector('[name="is_full"]').checked,
  };
}

/** 給油量の欄の下に、保存される値を出す（例：550 → = 5.50 L） */
function updateLitersPreview(form) {
  const raw = form.elements.liters.value.trim();
  const cl = fmt.parseLiters(raw);
  const preview = $('[data-d="liters-preview"]', form);
  preview.textContent = raw === '' ? '' : cl === null ? MSG_LITERS : `= ${fmt.centilitersText(cl)} L`;
  preview.classList.toggle('invalid', raw !== '' && cl === null);
}

function openFuelDialog(row) {
  const form = $('#fuel-form');
  form.reset();
  clearErrors(form);
  form.dataset.id = row ? String(row.id) : '';
  $('[data-d="title"]', form).textContent = row ? '給油記録の修正' : '給油記録の追加';
  form.elements.odo.value = row ? row.odo : '';
  form.elements.liters.value = row ? row.liters.toFixed(2) : '';
  form.elements.is_full.checked = row ? row.is_full : true;
  updateLitersPreview(form);
  $('[data-act="delete"]', form).hidden = !row;
  form.dataset.dirty = '';
  $('#fuel-dialog').showModal();
}

async function submitFuel(event) {
  event.preventDefault();
  const form = event.target;
  const id = form.dataset.id;
  // 1 L 未満は打ち間違い（5.00 L のつもりで 5）が多いので、確かめてから保存する（M28）
  const raw = form.elements.liters.value.trim();
  const cl = fmt.parseLiters(raw);
  if (raw !== '' && cl === null) {
    // 読めない値は送らない（欄の下に M18 がすでに赤字で出ている）
    form.elements.liters.focus();
    return;
  }
  if (cl !== null && cl < 100 && !confirm(MSG_SMALL_LITERS(fmt.centilitersText(cl)))) return;
  try {
    if (id) {
      await api('PUT', `/api/fuel-logs/${id}`, collectFuel(form));
    } else {
      await api('POST', `/api/vehicles/${state.vehicleId}/fuel-logs`, collectFuel(form));
      state.fuelPage = null;
    }
    $('#fuel-dialog').close();
    toast(MSG_SAVED);
    await Promise.all([loadSummary(), loadFuel()]);
  } catch (e) {
    if (e instanceof ApiError) showErrors(form, e); else handleError(e);
  }
}

async function deleteFuel() {
  const form = $('#fuel-form');
  const row = state.fuel.rows.find((r) => String(r.id) === form.dataset.id);
  if (!row) return;
  const message = `この給油記録を削除します。よろしいですか？（ODO ${fmt.km(row.odo)}　${fmt.liters(row.liters)}）`;
  if (!confirm(message)) return;
  try {
    await api('DELETE', `/api/fuel-logs/${row.id}`);
    $('#fuel-dialog').close();
    toast(MSG_DELETED);
    await Promise.all([loadSummary(), loadFuel()]);
  } catch (e) {
    if (e instanceof ApiError) showErrors(form, e); else handleError(e);
  }
}

/* ---------------- 画面全体 ---------------- */

async function loadCurrentTab() {
  if (state.tab === 'ride') {
    await loadRide();
  } else {
    await loadFuel();
  }
}

async function refresh() {
  if (state.vehicleId === null) return;
  await Promise.all([loadSummary(), loadCurrentTab()]);
}

function switchTab(tab) {
  state.tab = tab;
  $$('[data-tab]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === tab)));
  $('#panel-ride').hidden = tab !== 'ride';
  $('#panel-fuel').hidden = tab !== 'fuel';
  loadCurrentTab().catch(handleError);
}

/** 入力中に閉じようとしたら確認する（M33）。 */
function setupDialog(dialog, form, onSubmit, onDelete) {
  form.addEventListener('input', () => { form.dataset.dirty = '1'; });
  form.addEventListener('submit', onSubmit);
  $('[data-act="cancel"]', form).addEventListener('click', () => {
    if (form.dataset.dirty && !confirm(MSG_DISCARD)) return;
    dialog.close();
  });
  $('[data-act="delete"]', form).addEventListener('click', onDelete);
  dialog.addEventListener('cancel', (event) => {
    if (form.dataset.dirty && !confirm(MSG_DISCARD)) event.preventDefault();
  });
}

async function init() {
  $('#vehicle-select').addEventListener('change', (e) => { onVehicleChange(e).catch(handleError); });
  $$('[data-tab]').forEach((b) => b.addEventListener('click', () => switchTab(b.dataset.tab)));
  $('.summary-toggle').addEventListener('click', (e) => {
    const open = $('#summary').classList.toggle('open');
    e.currentTarget.setAttribute('aria-expanded', String(open));
  });
  $('#ride-add').addEventListener('click', () => openRideDialog(null));
  $('#fuel-add').addEventListener('click', () => openFuelDialog(null));
  setupDialog($('#ride-dialog'), $('#ride-form'), submitRide, deleteRide);
  setupDialog($('#fuel-dialog'), $('#fuel-form'), submitFuel, deleteFuel);
  $('#fuel-form').elements.liters.addEventListener('input', () => updateLitersPreview($('#fuel-form')));

  await loadVehicles();
  if (state.vehicleId === null) {
    location.href = '/vehicles/new';
    return;
  }
  localStorage.setItem(STORAGE_KEY, String(state.vehicleId));
  await refresh();
}

init().catch(handleError);
