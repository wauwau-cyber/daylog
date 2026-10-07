import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { Exercise, ExerciseProgress } from '../core/models';
import { todayIso } from '../core/dates';
import { formatPct, trendOf } from '../core/progress-format';
import { Sparkline } from './sparkline';

@Component({
  selector: 'app-exercises-page',
  imports: [FormsModule, Sparkline],
  templateUrl: './exercises-page.html',
  styleUrl: './exercises-page.scss',
})
export class ExercisesPage implements OnInit {
  private api = inject(ApiService);

  protected readonly exercises = signal<Exercise[]>([]);
  protected readonly progress = signal<Record<number, ExerciseProgress>>({});
  protected readonly formatPct = formatPct;
  protected readonly trendOf = trendOf;
  protected readonly name = signal('');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly notice = signal<string | null>(null);

  protected readonly active = computed(() => this.exercises().filter(e => !e.archived));
  protected readonly archived = computed(() => this.exercises().filter(e => e.archived));

  ngOnInit() {
    this.run(() => this.reload());
  }

  add() {
    const name = this.name().trim();
    if (!name) return;
    this.run(async () => {
      await this.api.createExercise(name);
      this.name.set('');
      await this.reload();
    });
  }

  remove(ex: Exercise) {
    this.run(async () => {
      const res = await this.api.deleteExercise(ex.id);
      this.notice.set(res.result === 'archived'
        ? `${ex.name} is hidden from the list. Its ${ex.set_count} logged sets stay in your history.`
        : `${ex.name} deleted.`);
      await this.reload();
    });
  }

  restore(ex: Exercise) {
    this.run(async () => {
      await this.api.restoreExercise(ex.id);
      await this.reload();
    });
  }

  protected weeklyBest(p: ExerciseProgress) {
    return p.weeks.map(w => w.best);
  }

  private async reload() {
    const [exercises, progress] = await Promise.all([
      this.api.getExercises(true),
      this.api.getProgress(todayIso()),
    ]);
    this.exercises.set(exercises);
    this.progress.set(Object.fromEntries(
      progress.filter(e => e.progress).map(e => [e.exercise_id, e.progress!]),
    ));
  }

  private async run(action: () => Promise<void>) {
    this.busy.set(true);
    this.error.set(null);
    this.notice.set(null);
    try {
      await action();
    } catch (e) {
      this.error.set((e as Error).message);
    } finally {
      this.busy.set(false);
    }
  }
}
