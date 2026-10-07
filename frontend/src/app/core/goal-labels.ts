import { Goal, GoalType, Pace } from './models';

export const GOAL_LABELS: Record<GoalType, string> = {
  lose: 'Lose weight',
  maintain: 'Maintain weight',
  gain: 'Build muscle',
};

export const PACE_HINTS: Record<Exclude<GoalType, 'maintain'>, Record<Pace, string>> = {
  lose: { slow: 'About 0.25 kg per week', normal: 'About 0.5 kg per week' },
  gain: { slow: 'Small surplus, minimal fat gain', normal: 'Moderate surplus, faster progress' },
};

export function describeGoal(goal: Goal): string {
  if (goal.goal === 'maintain' || !goal.pace) return GOAL_LABELS[goal.goal];
  return `${GOAL_LABELS[goal.goal]}, ${PACE_HINTS[goal.goal][goal.pace].toLowerCase()}`;
}
