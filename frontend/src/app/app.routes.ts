import { Routes } from '@angular/router';
import { profileGuard } from './core/profile.guard';
import { todayIso } from './core/dates';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: () => `/day/${todayIso()}` },
  {
    path: 'day/:date',
    canActivate: [profileGuard],
    loadComponent: () => import('./day/day-page').then(m => m.DayPage),
  },
  {
    path: 'exercises',
    canActivate: [profileGuard],
    loadComponent: () => import('./exercises/exercises-page').then(m => m.ExercisesPage),
  },
  {
    path: 'settings',
    canActivate: [profileGuard],
    loadComponent: () => import('./settings/settings-page').then(m => m.SettingsPage),
  },
  { path: 'setup', loadComponent: () => import('./setup/setup-page').then(m => m.SetupPage) },
  { path: '**', redirectTo: '' },
];
