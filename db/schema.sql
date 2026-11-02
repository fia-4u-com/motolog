-- Motolog（バイクのメンテナンス記録）
-- Copyright (C) 2026 合同会社FIA. All rights reserved.
-- 作成：Takeshi Hakamata
--
-- テーブルの定義（詳細設計書 2_DB定義）。管理用ユーザ motolog_admin で実行する。
--   mysql -u motolog_admin -p motolog < db/schema.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login_id      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_login_id (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address   VARCHAR(45)  NOT NULL,
  login_id     VARCHAR(50)  NOT NULL,
  succeeded    TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME     NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_ip (ip_address, attempted_at),
  KEY idx_login_attempts_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS vehicles (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                VARCHAR(50)  NOT NULL,
  vin                 VARCHAR(30)  NULL,
  plate               VARCHAR(30)  NULL,
  acquired_on         DATE         NOT NULL COMMENT '取得日（新車は登録日、中古車は取得した日）',
  first_registered_on DATE         NULL     COMMENT '初度登録年月（月の1日。中古車のみ）',
  image_file          VARCHAR(100) NULL,
  status              VARCHAR(10)  NOT NULL DEFAULT 'active' COMMENT 'active＝保有中、retired＝保有終了',
  sort_order          INT          NOT NULL DEFAULT 0,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id    INT UNSIGNED NOT NULL,
  name          VARCHAR(30)  NOT NULL,
  kind          VARCHAR(10)  NOT NULL COMMENT 'replace＝交換、setting＝設定値',
  measure       VARCHAR(10)  NULL     COMMENT 'distance＝距離、period＝期間（交換のみ）',
  initial_value INT          NULL     COMMENT '初期値（設定値のみ）',
  sort_order    INT          NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_items_vehicle_name (vehicle_id, name),
  CONSTRAINT fk_items_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS ride_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id INT UNSIGNED NOT NULL,
  ride_date  DATE         NOT NULL,
  odo        INT UNSIGNED NOT NULL,
  comment    VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ride_logs_vehicle_date (vehicle_id, ride_date),
  CONSTRAINT fk_ride_logs_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS ride_log_items (
  ride_log_id INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NOT NULL,
  delta       INT          NULL COMMENT '設定値の増減。交換の品目は NULL（行があること自体が「交換した」）',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ride_log_id, item_id),
  KEY idx_ride_log_items_item (item_id),
  CONSTRAINT fk_rli_ride_log FOREIGN KEY (ride_log_id) REFERENCES ride_logs (id) ON DELETE CASCADE,
  CONSTRAINT fk_rli_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS fuel_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id INT UNSIGNED NOT NULL,
  odo        INT UNSIGNED NOT NULL,
  liters     DECIMAL(6,2) NOT NULL,
  is_full    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1＝満タン、0＝継ぎ足し',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fuel_logs_vehicle_odo (vehicle_id, odo),
  CONSTRAINT fk_fuel_logs_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
