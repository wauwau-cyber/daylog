import { Component, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Tag } from '../core/models';
import { DayStore } from './day.store';

@Component({
  selector: 'app-notes-panel',
  imports: [FormsModule, RouterLink],
  template: `
    <section class="panel panel--notes">
      <header>
        <h2>Notes</h2>
        <a routerLink="/settings" fragment="tags" class="manage">Edit tags</a>
      </header>

      <div class="tags" role="group" aria-label="Day tags">
        @for (tag of choices(); track tag.id) {
          <button type="button" class="tag" [class.on]="isOn(tag)" [attr.aria-pressed]="isOn(tag)"
                  (click)="toggle(tag)">{{ tag.name }}</button>
        }
      </div>

      <textarea class="input note" rows="2" maxlength="5000" aria-label="Note for this day"
                placeholder="Anything worth remembering about today?"
                [(ngModel)]="text" (blur)="saveNote()"></textarea>
      <p class="status">
        @if (error()) { <span class="err">{{ error() }}</span> }
        @else if (saved()) { Saved }
      </p>
    </section>
  `,
  styles: `
    :host { display: block; }
    .panel--notes { --accent: #e7a3c8; }
    .manage { color: var(--muted); font-size: 0.85rem; &:hover { color: var(--text); } }
    .tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .tag {
      min-height: 32px;
      padding: 0 12px;
      border-radius: 999px;
      border: 1px solid var(--line-strong);
      background: transparent;
      color: var(--muted);
      font-size: 0.88rem;
      cursor: pointer;
      &:hover { color: var(--text); border-color: var(--faint); }
      &.on { background: #e7a3c826; border-color: #e7a3c880; color: var(--text); font-weight: 600; }
    }
    .note { min-height: 64px; resize: vertical; line-height: 1.45; }
    .status { margin: 4px 0 0; min-height: 1.2em; font-size: 0.8rem; color: var(--faint); }
    .err { color: var(--danger); }
  `,
})
export class NotesPanel {
  protected store = inject(DayStore);
  protected readonly text = signal('');
  protected readonly saved = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Active tags plus archived ones that are still set on this day. */
  protected readonly choices = computed(() => {
    const active = this.store.tags();
    const onDay = this.store.day()?.tags ?? [];
    return [...active, ...onDay.filter(t => !active.some(a => a.id === t.id))];
  });

  constructor() {
    effect(() => {
      this.text.set(this.store.day()?.note ?? '');
      this.saved.set(false);
    });
  }

  protected isOn(tag: Tag) {
    return (this.store.day()?.tags ?? []).some(t => t.id === tag.id);
  }

  protected async toggle(tag: Tag) {
    this.error.set(null);
    try {
      await this.store.toggleTag(tag);
    } catch (e) {
      this.error.set((e as Error).message);
    }
  }

  protected async saveNote() {
    const next = this.text().trim();
    if (next === (this.store.day()?.note ?? '')) return;
    this.error.set(null);
    try {
      await this.store.saveNote(next);
      this.saved.set(true);
    } catch (e) {
      this.error.set((e as Error).message);
    }
  }
}
