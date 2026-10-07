import { Component, computed, inject } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { describeGoal } from '../core/goal-labels';
import { DayStore } from './day.store';

type Status = 'hit' | 'under' | 'over' | 'none';

interface Meter {
  label: string;
  unit: string;
  actual: number | null;
  target: number;
  status: Status;
  statusText: string;
  fill: number;
}

/** Bars run to 130 % of the target, so overshooting stays visible. */
const SCALE = 1.3;

@Component({
  selector: 'app-goal-panel',
  imports: [DecimalPipe, RouterLink],
  templateUrl: './goal-panel.html',
  styleUrl: './goal-panel.scss',
})
export class GoalPanel {
  protected store = inject(DayStore);
  protected readonly markerPos = 100 / SCALE;

  protected readonly targets = computed(() => this.store.day()?.targets ?? null);
  protected readonly goalText = computed(() => {
    const t = this.targets();
    return t ? describeGoal(t.goal) : '';
  });
  protected readonly toTarget = computed(() => {
    const t = this.targets();
    const target = t?.goal.target_weight_kg;
    return t && target ? target - t.weight_kg : null;
  });

  /** Intake from food entries that already have nutrient values. */
  private readonly intake = computed(() => {
    const foods = this.store.day()?.foods ?? [];
    const known = foods.filter(f => f.calories_kcal !== null);
    const sum = (k: 'calories_kcal' | 'protein_g' | 'fiber_g') => known.reduce((a, f) => a + (f[k] ?? 0), 0);
    return {
      any: known.length > 0,
      missing: foods.length - known.length,
      kcal: sum('calories_kcal'),
      protein: sum('protein_g'),
      fiber: sum('fiber_g'),
    };
  });

  protected readonly missing = computed(() => this.intake().missing);
  protected readonly hasIntake = computed(() => this.intake().any);

  protected readonly meters = computed<Meter[]>(() => {
    const t = this.targets();
    if (!t) return [];
    const i = this.intake();
    return [
      this.calories(i.any ? i.kcal : null, t.expected_kcal),
      this.minimum('Protein', 'g', i.any ? i.protein : null, t.protein_g),
      this.minimum('Fiber', 'g', i.any ? i.fiber : null, t.fiber_g),
    ];
  });

  /** Calories count as hit within ±10 % of the target. */
  private calories(actual: number | null, target: number): Meter {
    const meter = this.base('Calories', 'kcal', actual, target);
    if (actual === null) return meter;
    const diff = Math.round(actual - target);
    if (Math.abs(diff) <= target * 0.1) return { ...meter, status: 'hit', statusText: 'On target' };
    return diff < 0
      ? { ...meter, status: 'under', statusText: `${(-diff).toLocaleString('en')} kcal under` }
      : { ...meter, status: 'over', statusText: `${diff.toLocaleString('en')} kcal over` };
  }

  /** Protein and fiber are minimums: 90 % counts as reached, more is fine. */
  private minimum(label: string, unit: string, actual: number | null, target: number): Meter {
    const meter = this.base(label, unit, actual, target);
    if (actual === null) return meter;
    return actual >= target * 0.9
      ? { ...meter, status: 'hit', statusText: 'Reached' }
      : { ...meter, status: 'under', statusText: `${Math.round(target - actual)} ${unit} to go` };
  }

  private base(label: string, unit: string, actual: number | null, target: number): Meter {
    const fill = actual === null ? 0 : Math.min(100, (actual / (target * SCALE)) * 100);
    return { label, unit, actual, target, fill, status: 'none', statusText: '' };
  }
}
