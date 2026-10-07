SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS user_profile (
  id          TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  birth_date  DATE NOT NULL,
  gender      ENUM('male','female','other') NOT NULL,
  height_cm   DECIMAL(5,1) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_single_profile CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weight_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_date    DATE NOT NULL UNIQUE,
  weight_kg   DECIMAL(5,2) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nutrient columns stay NULL until the AI analysis (or the user) fills them.
CREATE TABLE IF NOT EXISTS food_entries (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_date      DATE NOT NULL,
  description   VARCHAR(500) NOT NULL,
  calories_kcal DECIMAL(7,1) NULL,
  protein_g     DECIMAL(6,1) NULL,
  carbs_g       DECIMAL(6,1) NULL,
  sugar_g       DECIMAL(6,1) NULL,
  fat_g         DECIMAL(6,1) NULL,
  saturated_fat_g DECIMAL(6,1) NULL,
  fiber_g       DECIMAL(6,1) NULL,
  sodium_mg     DECIMAL(7,1) NULL,
  nutrients_source ENUM('ai','manual') NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_food_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exercises are archived instead of deleted once sets reference them,
-- so the history stays intact.
CREATE TABLE IF NOT EXISTS exercises (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL UNIQUE,
  archived_at TIMESTAMP NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_sets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_date    DATE NOT NULL,
  exercise_id INT UNSIGNED NOT NULL,
  reps        SMALLINT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sets_date (log_date),
  CONSTRAINT fk_sets_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every analysis run is kept; the newest per day is shown.
CREATE TABLE IF NOT EXISTS day_analyses (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_date        DATE NOT NULL,
  model           VARCHAR(100) NOT NULL,
  score           TINYINT UNSIGNED NULL,
  summary         TEXT NULL,
  calories_kcal   DECIMAL(7,1) NULL,
  protein_g       DECIMAL(6,1) NULL,
  carbs_g         DECIMAL(6,1) NULL,
  fat_g           DECIMAL(6,1) NULL,
  fiber_g         DECIMAL(6,1) NULL,
  result_json     JSON NOT NULL,
  input_json      JSON NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_analysis_date (log_date, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO exercises (name) VALUES
  ('Pull-ups'), ('Push-ups'), ('Dips'), ('Squats'), ('Lunges'), ('Sit-ups');
