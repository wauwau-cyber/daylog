export type Gender = 'male' | 'female' | 'other';
export type GoalType = 'lose' | 'maintain' | 'gain';
export type Pace = 'slow' | 'normal' | 'fast';

export interface Goal {
  goal: GoalType;
  pace: Pace | null;
  target_weight_kg: number | null;
  valid_from: string | null;
}

/** Calculated by the backend; training_kcal comes from the latest AI analysis. */
export interface Targets {
  goal: Goal;
  weight_kg: number;
  bmr_kcal: number;
  base_kcal: number;
  steps: number | null;
  steps_kcal: number;
  training_kcal: number | null;
  goal_adjustment_kcal: number;
  expected_kcal: number;
  floored_to_bmr: boolean;
  protein_g: number;
  fiber_g: number;
}

export interface Profile {
  birth_date: string;
  gender: Gender;
  height_cm: number;
  latest_weight_kg: number | null;
  goal: Goal | null;
}

export interface Nutrients {
  calories_kcal: number | null;
  protein_g: number | null;
  carbs_g: number | null;
  sugar_g: number | null;
  fat_g: number | null;
  saturated_fat_g: number | null;
  fiber_g: number | null;
  sodium_mg: number | null;
}

export interface FoodEntry extends Nutrients {
  id: number;
  description: string;
  nutrients_source: 'ai' | 'manual' | 'copied' | null;
  created_at: string;
}

export interface ExerciseSet {
  id: number;
  exercise_id: number;
  exercise_name: string;
  reps: number;
  created_at: string;
}

export interface Exercise {
  id: number;
  name: string;
  archived: boolean;
  set_count: number;
}

export interface AnalysisResult {
  score: number;
  summary: string;
  training_kcal: number;
  training_feedback: string;
  positives: string[];
  improvements: string[];
  tomorrow: string;
  totals: Record<keyof Nutrients, number>;
  targets: Targets | null;
}

export interface Analysis {
  id: number;
  model: string;
  created_at: string;
  result: AnalysisResult;
}

/** Progress of one exercise, measured by the best set (most reps in one set). */
export interface ExerciseProgress {
  current_best: number | null;
  best_4_weeks_ago: number | null;
  first_best: number | null;
  first_date: string;
  all_time_best: number;
  change_4_weeks_pct: number | null;
  change_since_start_pct: number | null;
  weeks: { from: string; best: number | null }[];
}

export interface ExerciseProgressEntry {
  exercise_id: number;
  name: string;
  archived: boolean;
  progress: ExerciseProgress | null;
}

export interface Tag {
  id: number;
  name: string;
  /** set for tags the app treats specially, e.g. 'rest_day' */
  system_key: string | null;
  archived?: boolean;
  day_count?: number;
}

export interface CalendarDay {
  sets: number;
  rest_day: boolean;
}

export interface Day {
  date: string;
  tags: Tag[];
  note: string | null;
  steps: number | null;
  targets: Targets | null;
  weight_kg: number | null;
  previous_weight: { date: string; weight_kg: number } | null;
  foods: FoodEntry[];
  sets: ExerciseSet[];
  progress: Record<number, ExerciseProgress>;
  analysis: Analysis | null;
}

export interface AppSettings {
  gemini_key_set: boolean;
  gemini_key_hint: string | null;
  gemini_model: string;
}

/** An earlier food, offered while typing. Nutrients are null until it was analyzed once. */
export interface FoodSuggestion extends Nutrients {
  source_id: number;
  description: string;
  uses: number;
  has_nutrients: boolean;
}

export interface VersionInfo {
  version: string;
  latest: string | null;
  release_url: string | null;
  update_available: boolean;
}
