import { Component, computed, effect, HostListener, inject, input, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { addDays, fromIso, isValidIso, todayIso, weekOf } from '../core/dates';
import { DayStore } from './day.store';
import { FoodPanel } from './food-panel';
import { TrainingPanel } from './training-panel';
import { AnalysisPanel } from './analysis-panel';
import { GoalPanel } from './goal-panel';
import { ActivityPanel } from './activity-panel';
import { NotesPanel } from './notes-panel';
import { Clock } from './clock';
import { CalendarDay } from '../core/models';

@Component({
  selector: 'app-day-page',
  imports: [RouterLink, DatePipe, FoodPanel, TrainingPanel, AnalysisPanel, GoalPanel, ActivityPanel, NotesPanel, Clock],
  providers: [DayStore],
  templateUrl: './day-page.html',
  styleUrl: './day-page.scss',
})
export class DayPage {
  private router = inject(Router);
  private api = inject(ApiService);
  /** Days of the visible week that have training, from the API. */
  private readonly calendar = signal<Record<string, CalendarDay>>({});
  protected store = inject(DayStore);

  /** Bound from the route param `:date`. */
  readonly date = input.required<string>();

  protected readonly today = todayIso();
  protected readonly dateObj = computed(() => fromIso(this.date()));
  protected readonly isToday = computed(() => this.date() === this.today);
  private readonly weekDays = computed(() => weekOf(this.date()));
  private readonly weekKey = computed(() => this.weekDays()[0]);
  protected readonly week = computed(() => {
    const cal = this.calendar();
    const day = this.store.day();
    return this.weekDays().map(iso => ({
      iso,
      date: fromIso(iso),
      // the open day uses live data, so changes show in the strip immediately
      trained: day?.date === iso ? day.sets.length > 0 : (cal[iso]?.sets ?? 0) > 0,
      rest: day?.date === iso ? day.tags.some(t => t.system_key === 'rest_day') : !!cal[iso]?.rest_day,
    }));
  });
  protected readonly relativeLabel = computed(() => {
    const diff = Math.round((this.dateObj().getTime() - fromIso(this.today).getTime()) / 86_400_000);
    if (diff === 0) return 'Today';
    if (diff === -1) return 'Yesterday';
    if (diff === 1) return 'Tomorrow';
    return diff < 0 ? `${-diff} days ago` : `In ${diff} days`;
  });

  constructor() {
    effect(() => {
      const date = this.date();
      if (!isValidIso(date)) {
        this.router.navigate(['/day', todayIso()], { replaceUrl: true });
        return;
      }
      this.store.load(date);
    });

    effect(() => {
      const monday = this.weekKey();
      if (!isValidIso(monday)) return;
      const days = weekOf(monday);
      this.api.getCalendar(days[0], days[6])
        .then(cal => this.calendar.set(cal))
        .catch(() => this.calendar.set({}));
    });
  }

  protected link(offset: number) {
    return ['/day', addDays(this.date(), offset)];
  }

  @HostListener('document:keydown', ['$event'])
  protected onKey(event: KeyboardEvent) {
    if (event.altKey || event.ctrlKey || event.metaKey) return;
    const target = event.target as HTMLElement;
    if (target.closest('input, textarea, select, [contenteditable]')) return;

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      this.router.navigate(this.link(event.key === 'ArrowLeft' ? -1 : 1));
    }
  }
}
