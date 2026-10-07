import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ApiService } from './api.service';

/** Sends the user to the setup screen until birth date, gender, height and weight exist. */
export const profileGuard: CanActivateFn = async () => {
  const api = inject(ApiService);
  const router = inject(Router);

  let profile = api.profile();
  if (profile === undefined) {
    try {
      profile = await api.loadProfile();
    } catch {
      return true; // let the page show the API error instead of looping
    }
  }
  return profile ? true : router.parseUrl('/setup');
};
