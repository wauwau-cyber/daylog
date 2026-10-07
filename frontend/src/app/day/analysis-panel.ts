import { Component, computed, inject, signal } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { DayStore } from './day.store';

@Component({
  selector: 'app-analysis-panel',
  imports: [DecimalPipe, DatePipe],
  templateUrl: './analysis-panel.html',
  styleUrl: './analysis-panel.scss',
})
export class AnalysisPanel {
  protected store = inject(DayStore);
  protected readonly error = signal<string | null>(null);

  protected readonly analysis = computed(() => this.store.day()?.analysis ?? null);
  protected readonly result = computed(() => this.analysis()?.result ?? null);
  protected readonly analyzedAt = computed(() => {
    const at = this.analysis()?.created_at;
    return at ? new Date(at.replace(' ', 'T')) : null;
  });
  protected readonly hasData = computed(() => {
    const d = this.store.day();
    return !!d && (d.foods.length > 0 || d.sets.length > 0 || d.weight_kg !== null);
  });

  async analyze() {
    this.error.set(null);
    try {
      await this.store.analyze();
    } catch (e) {
      this.error.set((e as Error).message);
    }
  }
}
