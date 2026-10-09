import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom, Observable } from 'rxjs';
import {
  AppSettings, CalendarDay, Day, Exercise, ExerciseProgressEntry, ExerciseSet, FoodEntry, FoodSuggestion,
  Gender, GoalType, Pace, Profile, Tag, VersionInfo,
} from './models';

export interface ProfileInput {
  birth_date: string;
  gender: Gender;
  height_cm: number;
  weight_kg: number;
  goal: GoalType;
  pace: Pace | null;
  target_weight_kg: number | null;
  today: string;
}

@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);

  /** Cached profile; `undefined` = not loaded yet, `null` = setup not done. */
  readonly profile = signal<Profile | null | undefined>(undefined);

  async loadProfile(): Promise<Profile | null> {
    const res = await this.call(this.http.get<{ profile: Profile | null }>('/api/profile'));
    this.profile.set(res.profile);
    return res.profile;
  }

  async saveProfile(input: ProfileInput): Promise<Profile> {
    const res = await this.call(this.http.post<{ profile: Profile }>('/api/profile', input));
    this.profile.set(res.profile);
    return res.profile;
  }

  getDay(date: string) {
    return this.call(this.http.get<Day>(`/api/days/${date}`));
  }

  saveWeight(date: string, weightKg: number | null) {
    return this.call(this.http.put<{ weight_kg: number | null }>(`/api/days/${date}/weight`, { weight_kg: weightKg }));
  }

  saveSteps(date: string, steps: number | null) {
    return this.call(this.http.put<{ steps: number | null }>(`/api/days/${date}/steps`, { steps }));
  }

  /** `fromFoodId` copies the nutrients of that earlier entry. */
  addFood(date: string, description: string, fromFoodId?: number) {
    return this.call(this.http.post<FoodEntry>(`/api/days/${date}/foods`, { description, from_food_id: fromFoodId }));
  }

  getFoodSuggestions(query: string, limit = 8) {
    const params = new URLSearchParams({ q: query, limit: String(limit) });
    return this.call(this.http.get<FoodSuggestion[]>(`/api/foods/suggestions?${params}`));
  }

  /** Hand correction; only sent fields change, nutrients become "manual". */
  updateFood(id: number, changes: Partial<Pick<FoodEntry, 'description' | 'calories_kcal' | 'protein_g' | 'carbs_g' | 'fat_g' | 'fiber_g'>>) {
    return this.call(this.http.put<FoodEntry>(`/api/foods/${id}`, changes));
  }

  deleteFood(id: number) {
    return this.call(this.http.delete<void>(`/api/foods/${id}`));
  }

  addSet(date: string, exerciseId: number, reps: number) {
    return this.call(this.http.post<ExerciseSet>(`/api/days/${date}/sets`, { exercise_id: exerciseId, reps }));
  }

  deleteSet(id: number) {
    return this.call(this.http.delete<void>(`/api/sets/${id}`));
  }

  getProgress(date: string) {
    return this.call(this.http.get<ExerciseProgressEntry[]>(`/api/progress?date=${date}`));
  }

  getCalendar(from: string, to: string) {
    return this.call(this.http.get<Record<string, CalendarDay>>(`/api/calendar?from=${from}&to=${to}`));
  }

  saveDayTags(date: string, tagIds: number[]) {
    return this.call(this.http.put<{ tags: Tag[] }>(`/api/days/${date}/tags`, { tag_ids: tagIds }));
  }

  saveNote(date: string, note: string) {
    return this.call(this.http.put<{ note: string | null }>(`/api/days/${date}/note`, { note }));
  }

  getTags(includeArchived = false) {
    return this.call(this.http.get<Tag[]>(`/api/tags${includeArchived ? '?include=archived' : ''}`));
  }

  createTag(name: string) {
    return this.call(this.http.post<Tag>('/api/tags', { name }));
  }

  deleteTag(id: number) {
    return this.call(this.http.delete<{ result: 'deleted' | 'archived' }>(`/api/tags/${id}`));
  }

  restoreTag(id: number) {
    return this.call(this.http.post<Tag>(`/api/tags/${id}/restore`, {}));
  }

  getVersion() {
    return this.call(this.http.get<VersionInfo>('/api/version'));
  }

  getSettings() {
    return this.call(this.http.get<AppSettings>('/api/settings'));
  }

  saveSettings(changes: { gemini_api_key?: string; gemini_model?: string }) {
    return this.call(this.http.put<AppSettings>('/api/settings', changes));
  }

  analyzeDay(date: string) {
    return this.call(this.http.post<Day>(`/api/days/${date}/analysis`, {}));
  }

  getExercises(includeArchived = false) {
    const query = includeArchived ? '?include=archived' : '';
    return this.call(this.http.get<Exercise[]>(`/api/exercises${query}`));
  }

  createExercise(name: string) {
    return this.call(this.http.post<Exercise>('/api/exercises', { name }));
  }

  deleteExercise(id: number) {
    return this.call(this.http.delete<{ result: 'deleted' | 'archived' }>(`/api/exercises/${id}`));
  }

  restoreExercise(id: number) {
    return this.call(this.http.post<Exercise>(`/api/exercises/${id}/restore`, {}));
  }

  /** Converts HTTP errors into readable messages from the API. */
  private async call<T>(request: Observable<T>): Promise<T> {
    try {
      return await firstValueFrom(request);
    } catch (e) {
      if (e instanceof HttpErrorResponse) {
        throw new Error(e.error?.error ?? (e.status === 0 ? 'API not reachable. Is Docker running?' : e.message));
      }
      throw e;
    }
  }
}
