import { ExerciseProgress } from './models';

/** Changes within ±3 % count as unchanged. */
const THRESHOLD = 3;

export type Trend = 'up' | 'down' | 'flat';

export function trendOf(pct: number | null): Trend | null {
  if (pct === null) return null;
  return pct >= THRESHOLD ? 'up' : pct <= -THRESHOLD ? 'down' : 'flat';
}

export function formatPct(pct: number): string {
  if (Math.abs(pct) < THRESHOLD) return '±0 %';
  return `${pct > 0 ? '+' : '−'}${Math.abs(pct)} %`;
}

/** Short line for the training block, e.g. "+20 % in 4 weeks". */
export function progressLine(p: ExerciseProgress): { text: string; trend: Trend | null } {
  if (p.change_4_weeks_pct !== null) {
    return { text: `${formatPct(p.change_4_weeks_pct)} in 4 weeks`, trend: trendOf(p.change_4_weeks_pct) };
  }
  if (p.change_since_start_pct !== null) {
    return { text: `${formatPct(p.change_since_start_pct)} since start`, trend: trendOf(p.change_since_start_pct) };
  }
  return { text: 'Baseline week', trend: null };
}
