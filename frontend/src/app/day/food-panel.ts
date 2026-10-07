import { Component, computed, effect, inject, signal } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { fromIso } from '../core/dates';
import { DayStore } from './day.store';
import { FoodInput, FoodPick } from './food-input';

@Component({
  selector: 'app-food-panel',
  imports: [FormsModule, DecimalPipe, DatePipe, FoodInput],
  templateUrl: './food-panel.html',
  styleUrl: './food-panel.scss',
})
export class FoodPanel {
  protected store = inject(DayStore);

  protected readonly weightText = signal<number | null>(null);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly foods = computed(() => this.store.day()?.foods ?? []);
  protected readonly previous = computed(() => this.store.day()?.previous_weight ?? null);
  protected readonly previousDate = computed(() => (this.previous() ? fromIso(this.previous()!.date) : null));
  protected readonly delta = computed(() => {
    const today = this.store.day()?.weight_kg;
    const prev = this.previous()?.weight_kg;
    return today != null && prev != null ? today - prev : null;
  });

  /** Sum of nutrients for entries that already have values. */
  protected readonly totals = computed(() => {
    const analysed = this.foods().filter(f => f.calories_kcal !== null);
    if (!analysed.length) return null;
    const sum = (key: 'calories_kcal' | 'protein_g' | 'carbs_g' | 'fat_g') =>
      analysed.reduce((acc, f) => acc + (f[key] ?? 0), 0);
    return {
      kcal: sum('calories_kcal'),
      protein: sum('protein_g'),
      carbs: sum('carbs_g'),
      fat: sum('fat_g'),
      missing: this.foods().length - analysed.length,
    };
  });

  constructor() {
    // keep the input in sync when the day changes
    effect(() => this.weightText.set(this.store.day()?.weight_kg ?? null));
  }

  async addFood(pick: FoodPick) {
    await this.run(() => this.store.addFood(pick.description, pick.fromFoodId));
  }

  async deleteFood(id: number) {
    await this.run(() => this.store.deleteFood(id));
  }

  async saveWeight() {
    const value = this.weightText();
    const current = this.store.day()?.weight_kg ?? null;
    const next = value === null || (value as unknown) === '' ? null : Number(value);
    if (next === current) return;
    await this.run(() => this.store.saveWeight(next));
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
