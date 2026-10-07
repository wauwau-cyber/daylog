import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { ApiService } from './api.service';
import { VersionInfo } from './models';

const DISMISSED_KEY = 'daylog.dismissedUpdate';

/** Shown when GitHub has a newer release than the running version. */
@Component({
  selector: 'app-update-banner',
  template: `
    @if (visible() && info(); as i) {
      <aside class="banner" role="status">
        <p>
          <strong>daylog {{ i.latest }} is available.</strong>
          You have {{ i.version }}. To update, double-click <code>update.bat</code> in your daylog folder.
          @if (i.release_url) {
            <a [href]="i.release_url" target="_blank" rel="noopener">What's new</a>
          }
        </p>
        <button type="button" (click)="dismiss()" aria-label="Hide until the next version">✕</button>
      </aside>
    }
  `,
  styles: `
    .banner {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-top: 12px;
      padding: 12px 14px 12px 16px;
      border-radius: 14px;
      border: 1px solid #b8a6ff55;
      background: #b8a6ff14;
      font-size: 0.92rem;
    }
    p { margin: 0; flex: 1; }
    strong { color: var(--ai); }
    code { font-size: 0.88em; padding: 1px 6px; border-radius: 6px; background: var(--surface-2); }
    a { color: var(--ai); margin-left: 6px; }
    button { border: 0; background: none; color: var(--muted); cursor: pointer; padding: 4px; font-size: 0.85rem; }
    button:hover { color: var(--text); }
  `,
})
export class UpdateBanner implements OnInit {
  private api = inject(ApiService);
  protected readonly info = signal<VersionInfo | null>(null);
  private readonly dismissed = signal<string | null>(this.read());

  protected readonly visible = computed(() => {
    const i = this.info();
    return !!i?.update_available && i.latest !== this.dismissed();
  });

  async ngOnInit() {
    try {
      this.info.set(await this.api.getVersion());
    } catch {
      // no banner when the check fails
    }
  }

  protected dismiss() {
    const latest = this.info()?.latest ?? null;
    this.dismissed.set(latest);
    try {
      if (latest) localStorage.setItem(DISMISSED_KEY, latest);
    } catch {
      // storage unavailable: hidden until reload
    }
  }

  private read(): string | null {
    try {
      return localStorage.getItem(DISMISSED_KEY);
    } catch {
      return null;
    }
  }
}
