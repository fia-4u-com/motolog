# Motolog（バイクのメンテナンス記録）

バイク1台ごとに、走行・給油・部品の交換を記録する Web アプリです。スマホのブラウザから使います。

- 走行記録と、そのときに交換・点検した部品の記録
- 給油記録と燃費（1回ごと・通算）
- 最終交換状況（オイル・フィルタを交換してからの距離、バッテリーの使用期間など）

## 動かす環境

- PHP 8.3、MySQL 8、nginx（Ubuntu 24.04 で運用）
- 外部ライブラリは使っていません

## フォルダの中身

| フォルダ | 中身 |
|---|---|
| public/ | 公開するファイル（index.php、画面の CSS・JavaScript） |
| src/ | アプリ本体 |
| templates/ | 画面のテンプレート |
| config/ | 設定の見本（config.sample.php） |
| db/ | テーブルの定義（schema.sql） |
| tools/ | ログインのパスワードを設定するコマンド |
| bin/、deploy/ | 毎日のバックアップと、手元PCへの取得 |
| tests/ | 単体テスト（DB は使いません） |

## 単体テスト

```
php tests/run.php
```

テストのデータは架空の値です。

## ライセンス

Copyright (C) 2026 合同会社FIA. All rights reserved.

複製・改変・再配布・他での利用はご遠慮ください。

作成：Takeshi Hakamata
