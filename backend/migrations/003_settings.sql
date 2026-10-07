SET NAMES utf8mb4;

-- Key/value settings editable in the app (e.g. the Gemini API key).
CREATE TABLE IF NOT EXISTS settings (
  name        VARCHAR(64) PRIMARY KEY,
  value       TEXT NULL,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
