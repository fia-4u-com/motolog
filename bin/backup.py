#!/usr/bin/env python3
# Motolog（バイクのメンテナンス記録）
# Copyright (C) 2026 合同会社FIA. All rights reserved.
# 作成：Takeshi Hakamata
#
# 毎日のバックアップ（詳細設計書 7_バックアップ／N-30）。
#   1. DB の中身を mysqldump で書き出す（接続情報は /etc/motolog/backup.cnf）
#   2. 車両画像（storage/uploads/）と一緒に motolog_YYYYMMDD.tar.gz にまとめる
#   3. 新しいものから21個を残し、古いものを削除する
# systemd の motolog-backup.service から、ユーザ motolog で実行される。
# 失敗したら終了コード 1 を返し、systemd に失敗として記録させる。

import datetime
import io
import os
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path

DB_NAME = "motolog"
DEFAULTS_FILE = Path("/etc/motolog/backup.cnf")
UPLOADS_DIR = Path("/var/www/motolog/storage/uploads")
BACKUP_DIR = Path("/var/backups/motolog")
KEEP = 21
PREFIX = "motolog_"
SUFFIX = ".tar.gz"


def log(message: str) -> None:
    print(message, flush=True)


def dump_database() -> bytes:
    result = subprocess.run(
        [
            "mysqldump",
            f"--defaults-extra-file={DEFAULTS_FILE}",
            "--single-transaction",
            "--no-tablespaces",
            "--default-character-set=utf8mb4",
            DB_NAME,
        ],
        capture_output=True,
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError("mysqldump に失敗しました: " + result.stderr.decode("utf-8", "replace").strip())
    return result.stdout


def make_archive(dump: bytes, target: Path) -> None:
    # 書き終わるまでは一時ファイルに書き、最後に名前を変える（途中で止まっても壊れたファイルを残さない）
    fd, tmp_name = tempfile.mkstemp(prefix=".tmp_", dir=BACKUP_DIR)
    os.close(fd)
    tmp = Path(tmp_name)
    try:
        with tarfile.open(tmp, "w:gz") as tar:
            info = tarfile.TarInfo("db.sql")
            info.size = len(dump)
            info.mtime = int(datetime.datetime.now().timestamp())
            tar.addfile(info, io.BytesIO(dump))
            if UPLOADS_DIR.is_dir():
                for path in sorted(UPLOADS_DIR.iterdir()):
                    if path.is_file() and not path.name.startswith("."):
                        tar.add(path, arcname=f"uploads/{path.name}")
        tmp.chmod(0o640)
        tmp.replace(target)
    except BaseException:
        tmp.unlink(missing_ok=True)
        raise


def prune() -> list[str]:
    files = sorted(p for p in BACKUP_DIR.glob(f"{PREFIX}*{SUFFIX}") if p.is_file())
    removed = []
    for old in files[:-KEEP] if len(files) > KEEP else []:
        old.unlink()
        removed.append(old.name)
    return removed


def main() -> int:
    try:
        today = datetime.date.today().strftime("%Y%m%d")
        target = BACKUP_DIR / f"{PREFIX}{today}{SUFFIX}"
        make_archive(dump_database(), target)
        log(f"バックアップを作成しました: {target} ({target.stat().st_size} bytes)")
        for name in prune():
            log(f"古いバックアップを削除しました: {name}")
        return 0
    except Exception as e:  # noqa: BLE001 - 失敗の内容を記録して終了コードで知らせる
        log(f"バックアップに失敗しました: {e}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
