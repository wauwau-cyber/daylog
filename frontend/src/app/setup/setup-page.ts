import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ApiService } from '../core/api.service';
import { todayIso } from '../core/dates';
import { Gender, GoalType, Pace } from '../core/models';
import { GOAL_LABELS, PACE_HINTS } from '../core/goal-labels';

@Component({
  selector: 'app-setup-page',
  imports: [FormsModule],
  templateUrl: './setup-page.html',
  styleUrl: './setup-page.scss',
})
export class SetupPage implements OnInit {
  private api = inject(ApiService);
  private router = inject(Router);

  protected readonly today = todayIso();
  protected readonly birthDate = signal('');
  protected readonly gender = signal<Gender | ''>('');
  protected readonly height = signal<number | null>(null);
  protected readonly weight = signal<number | null>(null);
  protected readonly goal = signal<GoalType>('maintain');
  protected readonly pace = signal<Pace>('normal');
  protected readonly targetWeight = signal<number | null>(null);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly isEdit = computed(() => !!this.api.profile());

  protected readonly genders: { value: Gender; label: string }[] = [
    { value: 'female', label: 'Female' },
    { value: 'male', label: 'Male' },
    { value: 'other', label: 'Other' },
  ];

  protected readonly goals: { value: GoalType; label: string }[] =
    (['lose', 'maintain', 'gain'] as GoalType[]).map(value => ({ value, label: GOAL_LABELS[value] }));

  protected readonly paces = computed(() => {
    const goal = this.goal();
    if (goal === 'maintain') return [];
    return (['slow', 'normal'] as Pace[]).map(value => ({
      value,
      label: value === 'slow' ? 'Slow' : 'Normal',
      hint: PACE_HINTS[goal][value],
    }));
  });

  async ngOnInit() {
    try {
      const profile = this.api.profile() ?? (await this.api.loadProfile());
      if (profile) {
        this.birthDate.set(profile.birth_date);
        this.gender.set(profile.gender);
        this.height.set(profile.height_cm);
        this.weight.set(profile.latest_weight_kg);
        if (profile.goal) {
          this.goal.set(profile.goal.goal);
          this.pace.set(profile.goal.pace ?? 'normal');
          this.targetWeight.set(profile.goal.target_weight_kg);
        }
      }
    } catch (e) {
      this.error.set((e as Error).message);
    }
  }

  async save() {
    const gender = this.gender();
    if (!this.birthDate() || !gender || !this.height() || !this.weight()) {
      this.error.set('Fill in all four fields.');
      return;
    }
    this.saving.set(true);
    this.error.set(null);
    try {
      await this.api.saveProfile({
        birth_date: this.birthDate(),
        gender,
        height_cm: Number(this.height()),
        weight_kg: Number(this.weight()),
        goal: this.goal(),
        pace: this.goal() === 'maintain' ? null : this.pace(),
        target_weight_kg: this.targetWeight() ? Number(this.targetWeight()) : null,
        today: this.today,
      });
      this.router.navigate(['/day', this.today]);
    } catch (e) {
      this.error.set((e as Error).message);
    } finally {
      this.saving.set(false);
    }
  }
}
