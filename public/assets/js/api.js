/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * API との通信。CSRF の合言葉を付け、失敗を ApiError にそろえる（詳細設計書 5_API共通）。
 */

const MSG_NETWORK = '通信できませんでした。電波の良いところで、もう一度お試しください。';
const MSG_ERROR = 'エラーが発生しました。時間をおいて、もう一度お試しください。';
const MSG_EXPIRED = 'ログインの有効期限が切れました。もう一度ログインしてください。';

export class ApiError extends Error {
  constructor(error) {
    super(error.message || MSG_ERROR);
    this.code = error.code || 'SERVER_ERROR';
    this.fields = error.fields || {};
  }
}

function csrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

/**
 * API を呼ぶ。成功なら data を返し、失敗なら ApiError を投げる。
 * body が FormData なら multipart で、それ以外は JSON で送る。
 */
export async function api(method, url, body) {
  const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
  if (method !== 'GET') {
    options.headers['X-CSRF-Token'] = csrfToken();
  }
  if (body instanceof FormData) {
    options.body = body;
  } else if (body !== undefined) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(url, options);
  } catch (e) {
    throw new ApiError({ code: 'NETWORK', message: MSG_NETWORK });
  }

  if (response.status === 401) {
    // 期限切れの案内（M04）は、移った先のログイン画面に出す（確認の窓は端末によって出ないことがあるため。結合テスト T61）
    location.href = '/login?expired=1';
    throw new ApiError({ code: 'UNAUTHENTICATED', message: MSG_EXPIRED });
  }

  let json;
  try {
    json = await response.json();
  } catch (e) {
    throw new ApiError({ code: 'SERVER_ERROR', message: MSG_ERROR });
  }
  if (!json || json.ok !== true) {
    throw new ApiError((json && json.error) || {});
  }
  return json.data;
}

/** 画面の下に短いお知らせを出す。 */
export function toast(message) {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = message;
  el.hidden = false;
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => { el.hidden = true; }, 2500);
}

/**
 * 入力チェックの失敗を、フォームの項目の横（data-field）と全体のメッセージ（data-d="error"）に出す。
 * 成功時は clearErrors で消す。
 */
export function showErrors(root, error) {
  clearErrors(root);
  const general = root.querySelector('[data-d="error"]');
  let shownInField = false;
  for (const [field, message] of Object.entries(error.fields || {})) {
    const key = field.startsWith('deltas.') ? 'deltas' : field;
    const el = root.querySelector(`[data-field="${CSS.escape(key)}"]`);
    if (el) {
      el.textContent = message;
      shownInField = true;
    }
  }
  if (general && (!shownInField || error.code !== 'VALIDATION')) {
    general.textContent = error.message;
    general.hidden = false;
  }
}

export function clearErrors(root) {
  root.querySelectorAll('[data-field]').forEach((el) => { el.textContent = ''; });
  const general = root.querySelector('[data-d="error"]');
  if (general) {
    general.textContent = '';
    general.hidden = true;
  }
}
