/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * S04 品目の設定：追加・編集・並べ替え・使用停止・削除（F-42／D04）。
 */
import { api, ApiError, toast, showErrors, clearErrors } from './api.js';

const list = document.getElementById('items-list');
const card = list.closest('.card');
const vehicleId = list.dataset.vehicleId;

function itemCard(item) {
  const el = document.getElementById('tpl-item').content.firstElementChild.cloneNode(true);
  el.dataset.id = item.id ?? '';
  el.dataset.hasRecords = item.has_records ? '1' : '';
  el.querySelector('[name="name"]').value = item.name ?? '';
  el.querySelector('[name="kind"]').value = item.kind ?? 'replace';
  el.querySelector('[name="measure"]').value = item.measure ?? 'distance';
  el.querySelector('[name="initial_value"]').value = item.initial_value ?? '';
  el.querySelector('[name="is_active"]').checked = item.is_active ?? true;
  // 記録のある品目は種別を変えられず、削除もできない（D04／M22）
  el.querySelector('[name="kind"]').disabled = !!item.has_records;
  el.querySelector('[data-act="delete"]').hidden = !!item.has_records;
  updateKind(el);
  return el;
}

function updateKind(el) {
  const kind = el.querySelector('[name="kind"]').value;
  el.querySelector('[data-for="replace"]').hidden = kind !== 'replace';
  el.querySelector('[data-for="setting"]').hidden = kind !== 'setting';
}

async function load() {
  const items = await api('GET', `/api/vehicles/${vehicleId}/items`);
  list.replaceChildren(...items.map(itemCard));
}

function collect() {
  return Array.from(list.children).map((el) => {
    const kind = el.querySelector('[name="kind"]').value;
    return {
      id: el.dataset.id ? Number(el.dataset.id) : null,
      name: el.querySelector('[name="name"]').value,
      kind,
      measure: kind === 'replace' ? el.querySelector('[name="measure"]').value : null,
      initial_value: kind === 'setting' ? el.querySelector('[name="initial_value"]').value : null,
      is_active: el.querySelector('[name="is_active"]').checked,
    };
  });
}

/** API の「items.3.name」の形のメッセージを、3番目の札の項目の横に出す。 */
function showItemErrors(error) {
  showErrors(card, error);
  const cards = Array.from(list.children);
  for (const [field, message] of Object.entries(error.fields || {})) {
    const m = /^items\.(\d+)\.(\w+)$/.exec(field);
    if (!m || !cards[Number(m[1])]) continue;
    const el = cards[Number(m[1])].querySelector(`[data-f="${m[2]}"]`);
    if (el) el.textContent = message;
  }
}

function clearItemErrors() {
  clearErrors(card);
  list.querySelectorAll('[data-f]').forEach((el) => { el.textContent = ''; });
}

async function save() {
  clearItemErrors();
  try {
    await api('PUT', `/api/vehicles/${vehicleId}/items`, { items: collect() });
    toast('保存しました。');
    await load();
  } catch (e) {
    if (e instanceof ApiError) showItemErrors(e); else toast('エラーが発生しました。時間をおいて、もう一度お試しください。');
  }
}

async function remove(el) {
  const name = el.querySelector('[name="name"]').value || '（名前なし）';
  if (!confirm(`品目「${name}」を削除します。よろしいですか？`)) return;
  if (!el.dataset.id) {
    el.remove();
    return;
  }
  try {
    await api('DELETE', `/api/items/${el.dataset.id}`);
    el.remove();
    toast('削除しました。');
  } catch (e) {
    if (e instanceof ApiError) showErrors(card, e); else toast('エラーが発生しました。');
  }
}

list.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-act]');
  if (!button) return;
  const el = button.closest('.item-card');
  switch (button.dataset.act) {
    case 'up':
      if (el.previousElementSibling) list.insertBefore(el, el.previousElementSibling);
      break;
    case 'down':
      if (el.nextElementSibling) list.insertBefore(el.nextElementSibling, el);
      break;
    case 'delete':
      remove(el);
      break;
    default:
      break;
  }
});
list.addEventListener('change', (event) => {
  if (event.target.name === 'kind') updateKind(event.target.closest('.item-card'));
});
card.querySelector('[data-act="add"]').addEventListener('click', () => {
  const el = itemCard({ kind: 'replace', measure: 'distance', is_active: true });
  list.append(el);
  el.querySelector('[name="name"]').focus();
});
card.querySelector('[data-act="save"]').addEventListener('click', save);

load().catch((e) => toast(e.message));
