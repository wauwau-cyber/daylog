SET NAMES utf8mb4;

-- Tags you can tap on a day. system_key marks tags the app treats specially (rest day in the week strip).
CREATE TABLE IF NOT EXISTS tags (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50) NOT NULL UNIQUE,
  system_key  VARCHAR(30) NULL UNIQUE,
  archived_at TIMESTAMP NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS day_tags (
  log_date    DATE NOT NULL,
  tag_id      INT UNSIGNED NOT NULL,
  PRIMARY KEY (log_date, tag_id),
  CONSTRAINT fk_day_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS day_notes (
  log_date    DATE PRIMARY KEY,
  note        TEXT NOT NULL,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tags (name, system_key) VALUES
  ('Rest day', 'rest_day'),
  ('Sick', 'sick'),
  ('Bad sleep', NULL),
  ('Stressed', NULL),
  ('Travel', NULL),
  ('Party / cheat day', NULL);
