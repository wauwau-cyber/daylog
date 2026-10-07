import { Component, ElementRef, inject, output, signal, viewChild } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { FoodSuggestion } from '../core/models';

export interface FoodPick {
  description: string;
  /** set when the user picked an earlier food with known nutrients */
  fromFoodId?: number;
}

const normalize = (text: string) => text.trim().replace(/\s+/g, ' ').toLowerCase();

/**
 * Text input with suggestions from earlier entries (most frequent first).
 * Picking one copies its nutrients, so it counts immediately without a new analysis.
 */
@Component({
  selector: 'app-food-input',
  imports: [FormsModule, DecimalPipe],
  templateUrl: './food-input.html',
  styleUrl: './food-input.scss',
})
export class FoodInput {
  private api = inject(ApiService);
  private input = viewChild.required<ElementRef<HTMLInputElement>>('input');

  readonly picked = output<FoodPick>();

  protected readonly text = signal('');
  protected readonly suggestions = signal<FoodSuggestion[]>([]);
  protected readonly open = signal(false);
  protected readonly active = signal(-1);
  protected readonly busy = signal(false);

  private timer?: ReturnType<typeof setTimeout>;
  private requestId = 0;

  protected onFocus() {
    this.open.set(true);
    this.load();
  }

  protected onInput(value: string) {
    this.text.set(value);
    this.open.set(true);
    this.active.set(-1);
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.load(), 150);
  }

  protected onKeydown(event: KeyboardEvent) {
    const list = this.suggestions();
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        this.open.set(true);
        if (list.length) this.active.set((this.active() + 1) % list.length);
        break;
      case 'ArrowUp':
        event.preventDefault();
        if (list.length) this.active.set(this.active() <= 0 ? list.length - 1 : this.active() - 1);
        break;
      case 'Enter':
        event.preventDefault();
        this.submit();
        break;
      case 'Escape':
        this.close();
        break;
    }
  }

  /** Enter or "+": the highlighted suggestion, else an exact text match, else the typed text. */
  submit() {
    const highlighted = this.open() ? this.suggestions()[this.active()] : undefined;
    if (highlighted) {
      this.choose(highlighted);
      return;
    }
    const text = this.text().trim();
    if (!text) return;
    const exact = this.suggestions().find(s => s.has_nutrients && normalize(s.description) === normalize(text));
    this.emit(exact ? { description: exact.description, fromFoodId: exact.source_id } : { description: text });
  }

  protected choose(s: FoodSuggestion) {
    this.emit({ description: s.description, fromFoodId: s.has_nutrients ? s.source_id : undefined });
  }

  protected close() {
    this.open.set(false);
    this.active.set(-1);
  }

  private emit(pick: FoodPick) {
    this.picked.emit(pick);
    this.text.set('');
    this.close();
    this.suggestions.set([]);
    this.input().nativeElement.focus();
  }

  private async load() {
    const id = ++this.requestId;
    try {
      const list = await this.api.getFoodSuggestions(this.text().trim());
      if (id === this.requestId) this.suggestions.set(list);
    } catch {
      if (id === this.requestId) this.suggestions.set([]);
    }
  }
}
