/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * S03 車両の登録・編集。画像はブラウザの中で 4:3 に切り取り、1200×900 の JPEG にして送る（D08／D12）。
 */
import { api, ApiError, toast, showErrors, clearErrors } from './api.js';

const OUT_W = 1200;
const OUT_H = 900;
const MAX_BYTES = 10 * 1024 * 1024;
const MSG_IMAGE = '画像は JPEG・PNG・WebP の形式で、10MBまでのファイルを選んでください。';

const form = document.getElementById('vehicle-form');
const card = form.closest('.card');
const vehicleId = form.dataset.vehicleId;
const cropBox = form.querySelector('.crop');
const canvas = form.querySelector('.crop-canvas');
const zoom = form.querySelector('.crop-zoom');
const result = form.querySelector('.crop-result');
const ctx = canvas.getContext('2d');

/* ---------------- 画像の切り取り ---------------- */

const crop = {
  image: null,
  base: 1,     // 枠いっぱいに画像を収めるための倍率
  scale: 1,    // base × 拡大率
  x: 0,        // 枠の左上に来る画像上の位置（画像のピクセル）
  y: 0,
  drag: null,
  blob: null,  // 切り取り済みの画像（保存時に送る）
};

function clampPosition() {
  const viewW = canvas.width / crop.scale;
  const viewH = canvas.height / crop.scale;
  crop.x = Math.min(Math.max(crop.x, 0), crop.image.width - viewW);
  crop.y = Math.min(Math.max(crop.y, 0), crop.image.height - viewH);
}

function draw() {
  ctx.fillStyle = '#000';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  if (!crop.image) return;
  ctx.drawImage(
    crop.image, crop.x, crop.y, canvas.width / crop.scale, canvas.height / crop.scale,
    0, 0, canvas.width, canvas.height,
  );
}

function setZoom(z) {
  const centerX = crop.x + canvas.width / crop.scale / 2;
  const centerY = crop.y + canvas.height / crop.scale / 2;
  crop.scale = crop.base * z;
  crop.x = centerX - canvas.width / crop.scale / 2;
  crop.y = centerY - canvas.height / crop.scale / 2;
  clampPosition();
  draw();
}

async function onFileChange(event) {
  const file = event.target.files[0];
  const error = form.querySelector('[data-field="image"]');
  error.textContent = '';
  crop.blob = null;
  result.hidden = true;
  if (!file) return;
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > MAX_BYTES) {
    error.textContent = MSG_IMAGE;
    event.target.value = '';
    return;
  }
  try {
    crop.image = await createImageBitmap(file);
  } catch (e) {
    error.textContent = MSG_IMAGE;
    return;
  }
  // 4:3 の枠を覆う最小の倍率から始める
  crop.base = Math.max(canvas.width / crop.image.width, canvas.height / crop.image.height);
  crop.scale = crop.base;
  zoom.value = '1';
  crop.x = (crop.image.width - canvas.width / crop.scale) / 2;
  crop.y = (crop.image.height - canvas.height / crop.scale) / 2;
  cropBox.hidden = false;
  draw();
}

function onPointerDown(event) {
  if (!crop.image) return;
  canvas.setPointerCapture(event.pointerId);
  crop.drag = { px: event.clientX, py: event.clientY, x: crop.x, y: crop.y };
}

function onPointerMove(event) {
  if (!crop.drag) return;
  // 画面上の移動量を、画像上の移動量に直す（表示の大きさと canvas の大きさの比も考える）
  const ratio = canvas.width / canvas.getBoundingClientRect().width;
  crop.x = crop.drag.x - ((event.clientX - crop.drag.px) * ratio) / crop.scale;
  crop.y = crop.drag.y - ((event.clientY - crop.drag.py) * ratio) / crop.scale;
  clampPosition();
  draw();
}

function onPointerUp() {
  crop.drag = null;
}

function onCropDone() {
  const out = document.createElement('canvas');
  out.width = OUT_W;
  out.height = OUT_H;
  out.getContext('2d').drawImage(
    crop.image, crop.x, crop.y, canvas.width / crop.scale, canvas.height / crop.scale, 0, 0, OUT_W, OUT_H,
  );
  out.toBlob((blob) => {
    crop.blob = blob;
    result.src = URL.createObjectURL(blob);
    result.hidden = false;
    cropBox.hidden = true;
  }, 'image/jpeg', 0.9);
}

/* ---------------- 保存 ---------------- */

function collect() {
  const data = {};
  for (const name of ['name', 'vin', 'plate', 'acquired_on', 'first_registered_on', 'status', 'copy_items_from']) {
    const el = form.elements[name];
    if (el) data[name] = el.value;
  }
  return data;
}

async function onSubmit(event) {
  event.preventDefault();
  clearErrors(card);
  if (crop.image && !crop.blob && !cropBox.hidden) {
    form.querySelector('[data-field="image"]').textContent = '「この範囲で決定」を押してから保存してください。';
    return;
  }
  try {
    let id = vehicleId;
    if (id) {
      await api('PUT', `/api/vehicles/${id}`, collect());
    } else {
      id = (await api('POST', '/api/vehicles', collect())).id;
    }
    if (crop.blob) {
      const body = new FormData();
      body.append('image', crop.blob, 'vehicle.jpg');
      await api('POST', `/api/vehicles/${id}/image`, body);
    }
    toast('保存しました。');
    localStorage.setItem('motolog.vehicle', String(id));
    location.href = vehicleId ? '/' : `/vehicles/${id}/items`;
  } catch (e) {
    if (e instanceof ApiError) {
      showErrors(card, e);
    } else {
      console.error(e);
      toast('エラーが発生しました。時間をおいて、もう一度お試しください。');
    }
  }
}

form.elements.image.addEventListener('change', onFileChange);
canvas.addEventListener('pointerdown', onPointerDown);
canvas.addEventListener('pointermove', onPointerMove);
canvas.addEventListener('pointerup', onPointerUp);
canvas.addEventListener('pointercancel', onPointerUp);
zoom.addEventListener('input', () => setZoom(Number(zoom.value)));
form.querySelector('[data-act="crop-done"]').addEventListener('click', onCropDone);
form.addEventListener('submit', onSubmit);
