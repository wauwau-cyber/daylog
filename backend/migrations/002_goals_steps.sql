SET NAMES utf8mb4;

-- Goals are versioned: the row with the latest valid_from <= day applies to that day.
CREATE TABLE IF NOT EXISTS goals (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  valid_from       DATE NOT NULL UNIQUE,
  goal             ENUM('lose','maintain','gain') NOT NULL,
  pace             ENUM('slow','normal') NULL,
  target_weight_kg DECIMAL(5,2) NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS step_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_date    DATE NOT NULL UNIQUE,
  steps       INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
