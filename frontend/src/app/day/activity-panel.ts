import { Component, computed, effect, inject, signal } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DayStore } from './day.store';

@Component({
  selector: 'app-activity-panel',
  imports: [FormsModule, DecimalPipe],
  template: `
    <section class="panel panel--activity">
      <header>
        <h2>Activity</h2>
        @if (stepsKcal() !== null) {
          <span class="burn num">+{{ stepsKcal() | number: '1.0-0' }} kcal</span>
        }
      </header>
      <label class="field">
        <span>Steps</span>
        <input class="input num" type="number" inputmode="numeric" min="0" max="200000" step="100"
               placeholder="e.g. 8000" [(ngModel)]="stepsText"
               (blur)="save()" (keydown.enter)="save()" />
      </label>
      @if (error()) {
        <p class="error">{{ error() }}</p>
      }
      <p class="hint">
        @if (store.day()?.steps === null) {
          Take the number from your phone or watch. Steps raise your calorie target for the day.
        } @else {
          Added to today's calorie target.
        }
      </p>
    </section>
  `,
  styles: `
    :host { display: block; }
    .panel--activity { --accent: #8fb8ff; }
    .burn { color: var(--accent); font-weight: 600; }
    .field { max-width: 200px; }
    .hint { margin: 10px 0 0; color: var(--muted); font-size: 0.88rem; }
  `,
})
export class ActivityPanel {
  protected store = inject(DayStore);
  protected readonly stepsText = signal<number | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly stepsKcal = computed(() => {
    const t = this.store.day()?.targets;
    return t && t.steps !== null ? t.steps_kcal : null;
  });

  constructor() {
    effect(() => this.stepsText.set(this.store.day()?.steps ?? null));
  }

  async save() {
    const raw = this.stepsText();
    const next = raw === null || (raw as unknown) === '' ? null : Math.round(Number(raw));
    if (next === (this.store.day()?.steps ?? null)) return;
    this.error.set(null);
    try {
      await this.store.saveSteps(next);
    } catch (e) {
      this.error.set((e as Error).message);
    }
  }
}
