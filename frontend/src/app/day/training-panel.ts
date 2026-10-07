import { Component, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ExerciseProgress, ExerciseSet } from '../core/models';
import { progressLine, Trend } from '../core/progress-format';
import { DayStore } from './day.store';

interface SetGroup {
  exerciseId: number;
  name: string;
  total: number;
  sets: ExerciseSet[];
  best: number | null;
  progress: { text: string; trend: Trend | null } | null;
}

@Component({
  selector: 'app-training-panel',
  imports: [FormsModule, RouterLink],
  templateUrl: './training-panel.html',
  styleUrl: './training-panel.scss',
})
export class TrainingPanel {
  protected store = inject(DayStore);

  protected readonly exerciseId = signal<number | null>(null);
  protected readonly reps = signal<number | null>(null);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Sets grouped by exercise, in the order the exercise was first done that day. */
  protected readonly groups = computed<SetGroup[]>(() => {
    const map = new Map<number, SetGroup>();
    const progress = this.store.day()?.progress ?? {};
    for (const set of this.store.day()?.sets ?? []) {
      let group = map.get(set.exercise_id);
      if (!group) {
        const p: ExerciseProgress | undefined = progress[set.exercise_id];
        group = {
          exerciseId: set.exercise_id,
          name: set.exercise_name,
          total: 0,
          sets: [],
          best: p?.current_best ?? null,
          progress: p ? progressLine(p) : null,
        };
        map.set(set.exercise_id, group);
      }
      group.sets.push(set);
      group.total += set.reps;
    }
    return [...map.values()];
  });

  protected readonly totalSets = computed(() => this.store.day()?.sets.length ?? 0);
  protected readonly canAdd = computed(() => !!this.exerciseId() && (this.reps() ?? 0) > 0 && !this.busy());

  constructor() {
    // preselect the first exercise once the list is loaded
    effect(() => {
      const list = this.store.exercises();
      if (this.exerciseId() === null && list.length) this.exerciseId.set(list[0].id);
    });
  }

  /** Reps stay filled in, so repeating a set is a single click. */
  async addSet() {
    if (!this.canAdd()) return;
    await this.run(() => this.store.addSet(this.exerciseId()!, Number(this.reps())));
  }

  async deleteSet(id: number) {
    await this.run(() => this.store.deleteSet(id));
  }

  private async run(action: () => Promise<void>) {
    this.busy.set(true);
    this.error.set(null);
    try {
      await action();
    } catch (e) {
      this.error.set((e as Error).message);
    } finally {
      this.busy.set(false);
    }
  }
}
