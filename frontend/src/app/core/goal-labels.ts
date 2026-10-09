import { Goal, GoalType, Pace } from './models';

export const GOAL_LABELS: Record<GoalType, string> = {
  lose: 'Lose weight',
  maintain: 'Maintain weight',
  gain: 'Build muscle',
};

/** Paces offered per goal; "fast" only makes sense for losing weight. */
export const PACES: Record<Exclude<GoalType, 'maintain'>, Pace[]> = {
  lose: ['slow', 'normal', 'fast'],
  gain: ['slow', 'normal'],
};

export const PACE_LABELS: Record<Pace, string> = { slow: 'Slow', normal: 'Normal', fast: 'Fast' };

export const PACE_HINTS: Record<Exclude<GoalType, 'maintain'>, Partial<Record<Pace, string>>> = {
  lose: {
    slow: 'About 0.25 kg per week',
    normal: 'About 0.5 kg per week',
    fast: 'About 0.75 kg per week. Best for shorter phases; keep protein high',
  },
  gain: { slow: 'Small surplus, minimal fat gain', normal: 'Moderate surplus, faster progress' },
};

export function describeGoal(goal: Goal): string {
  if (goal.goal === 'maintain' || !goal.pace) return GOAL_LABELS[goal.goal];
  // first sentence only ("About 0.75 kg per week. Best for ...")
  const hint = PACE_HINTS[goal.goal][goal.pace]?.split('. ')[0];
  return hint ? `${GOAL_LABELS[goal.goal]}, ${hint.toLowerCase()}` : GOAL_LABELS[goal.goal];
}
