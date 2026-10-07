import { Component, DestroyRef, inject, signal } from '@angular/core';

const pad = (n: number) => String(n).padStart(2, '0');

/** Current local time, HH:mm:ss, updated every second. */
@Component({
  selector: 'app-clock',
  template: `<time class="num" [attr.datetime]="iso()">{{ text() }}</time>`,
  styles: `
    :host { display: block; }
    time {
      font-family: var(--font-display);
      font-size: 1.05rem;
      font-weight: 500;
      letter-spacing: 0.04em;
      color: var(--faint);
    }
  `,
})
export class Clock {
  protected readonly text = signal('');
  protected readonly iso = signal('');

  constructor() {
    this.tick();
    const timer = setInterval(() => this.tick(), 1000);
    inject(DestroyRef).onDestroy(() => clearInterval(timer));
  }

  private tick() {
    const now = new Date();
    this.text.set(`${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`);
    this.iso.set(now.toISOString());
  }
}
