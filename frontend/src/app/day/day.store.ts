import { Injectable, inject, signal } from '@angular/core';
import { ApiService } from '../core/api.service';
import { Day, Exercise, Tag } from '../core/models';

/** State for the day page. Provided per page instance. */
@Injectable()
export class DayStore {
  private api = inject(ApiService);

  readonly date = signal('');
  readonly day = signal<Day | null>(null);
  readonly exercises = signal<Exercise[]>([]);
  /** Active tags to choose from. */
  readonly tags = signal<Tag[]>([]);
  readonly loading = signal(false);
  readonly analyzing = signal(false);
  readonly error = signal<string | null>(null);

  async load(date: string) {
    this.date.set(date);
    this.loading.set(true);
    this.error.set(null);
    try {
      const [day, exercises, tags] = await Promise.all([
        this.api.getDay(date),
        this.exercises().length ? Promise.resolve(this.exercises()) : this.api.getExercises(),
        this.tags().length ? Promise.resolve(this.tags()) : this.api.getTags(),
      ]);
      if (this.date() !== date) return; // user navigated on meanwhile
      this.day.set(day);
      this.exercises.set(exercises);
      this.tags.set(tags);
    } catch (e) {
      this.error.set((e as Error).message);
    } finally {
      if (this.date() === date) this.loading.set(false);
    }
  }

  async saveWeight(weightKg: number | null) {
    const date = this.date();
    const res = await this.api.saveWeight(date, weightKg);
    this.patch(date, d => ({ ...d, weight_kg: res.weight_kg }));
    await this.refresh(date); // targets depend on weight
  }

  async saveSteps(steps: number | null) {
    const date = this.date();
    const res = await this.api.saveSteps(date, steps);
    this.patch(date, d => ({ ...d, steps: res.steps }));
    await this.refresh(date); // targets depend on steps
  }

  /** Reload the day quietly (no loading state), e.g. to get recalculated targets. */
  async refresh(date: string) {
    const day = await this.api.getDay(date);
    if (this.date() === date) this.day.set(day);
  }

  async toggleTag(tag: Tag) {
    const date = this.date();
    const current = this.day()?.tags ?? [];
    const has = current.some(t => t.id === tag.id);
    const ids = has ? current.filter(t => t.id !== tag.id).map(t => t.id) : [...current.map(t => t.id), tag.id];
    // optimistic, so tapping feels instant
    this.patch(date, d => ({ ...d, tags: has ? d.tags.filter(t => t.id !== tag.id) : [...d.tags, tag] }));
    try {
      const res = await this.api.saveDayTags(date, ids);
      this.patch(date, d => ({ ...d, tags: res.tags }));
    } catch (e) {
      this.patch(date, d => ({ ...d, tags: current }));
      throw e;
    }
  }

  async saveNote(note: string) {
    const date = this.date();
    const res = await this.api.saveNote(date, note);
    this.patch(date, d => ({ ...d, note: res.note }));
  }

  async addFood(description: string, fromFoodId?: number) {
    const date = this.date();
    const food = await this.api.addFood(date, description, fromFoodId);
    this.patch(date, d => ({ ...d, foods: [...d.foods, food] }));
  }

  async deleteFood(id: number) {
    await this.api.deleteFood(id);
    this.patch(this.date(), d => ({ ...d, foods: d.foods.filter(f => f.id !== id) }));
  }

  async addSet(exerciseId: number, reps: number) {
    const date = this.date();
    const set = await this.api.addSet(date, exerciseId, reps);
    this.patch(date, d => ({ ...d, sets: [...d.sets, set] }));
    await this.refresh(date); // best set / progress may change
  }

  async deleteSet(id: number) {
    await this.api.deleteSet(id);
    const date = this.date();
    this.patch(date, d => ({ ...d, sets: d.sets.filter(s => s.id !== id) }));
    await this.refresh(date);
  }

  async analyze() {
    const date = this.date();
    this.analyzing.set(true);
    try {
      const day = await this.api.analyzeDay(date);
      if (this.date() === date) this.day.set(day);
    } finally {
      this.analyzing.set(false);
    }
  }

  private patch(date: string, fn: (day: Day) => Day) {
    const day = this.day();
    if (day && day.date === date) this.day.set(fn(day));
  }
}
