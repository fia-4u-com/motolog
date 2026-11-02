/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 数値の見た目（詳細設計書 3_計算仕様「数値の丸めと表示」）。
 * API は数値のまま返し、「km」や3桁区切りはここで付ける（N-23）。
 */

export const DASH = '－';

const int = new Intl.NumberFormat('ja-JP', { maximumFractionDigits: 0 });
const two = new Intl.NumberFormat('ja-JP', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** 7052 → "7,052 km" */
export function km(value) {
  return value === null || value === undefined ? DASH : `${int.format(value)} km`;
}

/** 8.5 → "8.50 L" */
export function liters(value) {
  return value === null || value === undefined ? DASH : `${two.format(value)} L`;
}

/** 16.12 → "16.12 km/L" */
export function economy(value) {
  return value === null || value === undefined ? DASH : `${two.format(value)} km/L`;
}

/**
 * 経過年月日（例：2年7ヶ月5日、3ヶ月12日、5日、0日）。
 * v1.1 で、見た目を軽くするためゼロ埋め（07・05）をやめた。サーバ側の Elapsed::format は、
 * 旧システムとの照合（移行の検証・単体テスト）に使うので、旧システムと同じゼロ埋めの形のまま。
 */
export function elapsed(e) {
  if (!e) return DASH;
  let text = '';
  if (e.y > 0) text += `${e.y}年`;
  if (e.m > 0) text += `${e.m}ヶ月`;
  if (e.d > 0) text += `${e.d}日`;
  return text === '' ? '0日' : text;
}

/** 設定値：+2(8)／-1(7)／±0(8) */
export function setting(delta, value) {
  const sign = delta > 0 ? `+${delta}` : delta < 0 ? `${delta}` : '±0';
  return `${sign}(${value})`;
}

/**
 * 走行記録の「整備内容」の1件（C-03〜C-05）。
 *   交換：オイル(2,157km)／バッテリー(2年03ヶ月)／Fタイヤ(新車から 3,545km)
 *   設定値：Fプリロード +2(8)
 * 新車時の起点の記録（新車から 0km／0日）は、カッコを付けず品目名だけにする。
 */
export function event(e) {
  if (e.type === 'setting') return `${e.name} ${setting(e.delta, e.value)}`;
  const zero = e.type === 'period' ? e.value.y + e.value.m + e.value.d === 0 : e.value === 0;
  if (e.from_new && zero) return e.name;
  const value = e.type === 'period' ? elapsed(e.value) : int.format(e.value) + 'km';
  return `${e.name}(${e.from_new ? '新車から ' : ''}${value})`;
}

/**
 * 給油量の入力を 1/100 L の整数にする（F-22。製作報告書 B04）。読めなければ null。
 *   小数点なし：下2桁を小数とみなす　550 → 550（5.50 L）、1187 → 1187（11.87 L）、5 → 5（0.05 L）
 *   小数点あり：その位置を尊重する　　5.5 → 550、11.87 → 1187、5. → 500
 * 全角の数字・小数点も受け付ける。範囲は 0.01〜99.99 L。
 */
export function parseLiters(text) {
  const s = String(text ?? '').trim()
    .replace(/[０-９]/g, (c) => String.fromCharCode(c.charCodeAt(0) - 0xFEE0))
    .replace(/[．。]/g, '.');
  let cl = null;
  if (/^\d{1,4}$/.test(s)) {
    cl = Number(s);
  } else {
    const m = /^(\d{0,2})\.(\d{0,2})$/.exec(s);
    if (m && (m[1] !== '' || m[2] !== '')) cl = Number(m[1] || 0) * 100 + Number(m[2].padEnd(2, '0'));
  }
  return cl !== null && cl >= 1 && cl <= 9999 ? cl : null;
}

/** 1/100 L の整数を "5.50" の形に */
export function centilitersText(cl) {
  return `${Math.floor(cl / 100)}.${String(cl % 100).padStart(2, '0')}`;
}

/** 今日の日付 "YYYY-MM-DD"（端末の時刻で） */
export function today() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}
