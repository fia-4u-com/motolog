# Motolog（バイクのメンテナンス記録）
# Copyright (C) 2026 合同会社FIA. All rights reserved.
# 作成：Takeshi Hakamata
#
# 手元PCへのバックアップ取得（詳細設計書 7_バックアップ／Q20／D14／D15）。
#   サーバの /var/backups/motolog/ から、手元にないファイルだけを取得し、手元では新しいものから21個を残す。
#   取りこぼしの通知はしない（Q20）。
# タスクスケジューラから、PCへのログオン時と毎日 20:00 に実行する（設定はリリース手順書 B-9）。
#
# 使い方の例：
#   powershell -NoProfile -ExecutionPolicy Bypass -File Get-MotologBackup.ps1 `
#     -LocalDir "$env:OneDriveCommercial\バックアップ\Motolog" -KeyFile "$env:USERPROFILE\.ssh\motolog_backup" `
#     -ServerHost "example.com" -User "backupuser"
# 接続先とユーザ名は、ソースに書かずにタスクの設定で渡す。

param(
    [Parameter(Mandatory = $true)] [string] $LocalDir,
    [Parameter(Mandatory = $true)] [string] $KeyFile,
    [Parameter(Mandatory = $true)] [string] $ServerHost,
    [Parameter(Mandatory = $true)] [string] $User,
    [string] $RemoteDir = '/var/backups/motolog',
    [int] $Keep = 21
)

$ErrorActionPreference = 'Stop'
$logFile = Join-Path $LocalDir 'get-backup.log'

function Write-Log([string] $message) {
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message
    Add-Content -Path $logFile -Value $line -Encoding utf8
}

New-Item -ItemType Directory -Force -Path $LocalDir | Out-Null

# 鍵だけで接続し、パスワードを聞かない（BatchMode）
$sshOptions = @('-i', $KeyFile, '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=20')

try {
    $remote = & ssh @sshOptions "$User@$ServerHost" "ls -1 $RemoteDir" 2>$null
    if ($LASTEXITCODE -ne 0) { throw "サーバの一覧を取得できませんでした（終了コード $LASTEXITCODE）" }
    $remoteFiles = @($remote | Where-Object { $_ -match '^motolog_\d{8}\.tar\.gz$' })

    $got = 0
    foreach ($name in $remoteFiles) {
        $dest = Join-Path $LocalDir $name
        if (Test-Path $dest) { continue }
        & scp @sshOptions "${User}@${ServerHost}:$RemoteDir/$name" $dest 2>$null
        if ($LASTEXITCODE -ne 0) { throw "$name を取得できませんでした（終了コード $LASTEXITCODE）" }
        $got++
    }

    # 手元では新しいものから $Keep 個を残す（ファイル名の日付順）
    $local = @(Get-ChildItem -Path $LocalDir -Filter 'motolog_*.tar.gz' | Sort-Object Name)
    $removed = 0
    if ($local.Count -gt $Keep) {
        $local | Select-Object -First ($local.Count - $Keep) | ForEach-Object { Remove-Item $_.FullName; $removed++ }
    }
    Write-Log "取得 $got 件、削除 $removed 件、手元 $([Math]::Min($local.Count, $Keep)) 件"
}
catch {
    Write-Log "失敗：$($_.Exception.Message)"
    exit 1
}
