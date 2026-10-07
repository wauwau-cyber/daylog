import { Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';
import { ApiService } from './core/api.service';
import { todayIso } from './core/dates';
import { UpdateBanner } from './core/update-banner';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, UpdateBanner],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private api = inject(ApiService);
  private router = inject(Router);

  protected readonly hasProfile = computed(() => !!this.api.profile());
  protected readonly onDayRoute = toSignal(
    this.router.events.pipe(
      filter(e => e instanceof NavigationEnd),
      map(e => e.urlAfterRedirects.startsWith('/day')),
    ),
    { initialValue: false },
  );

  protected today() {
    return `/day/${todayIso()}`;
  }
}
