import { Component, computed, input } from '@angular/core';

/** Tiny line chart of weekly best sets; weeks without training are skipped. */
@Component({
  selector: 'app-sparkline',
  template: `
    <svg [attr.viewBox]="'0 0 ' + width + ' ' + height" [attr.width]="width" [attr.height]="height"
         role="img" [attr.aria-label]="label()">
      @if (line(); as l) {
        <polyline [attr.points]="l.points" />
        <circle [attr.cx]="l.last.x" [attr.cy]="l.last.y" r="3" />
      }
    </svg>
  `,
  styles: `
    :host { display: block; }
    svg { display: block; overflow: visible; }
    polyline { fill: none; stroke: var(--train); stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
    circle { fill: var(--train); }
  `,
})
export class Sparkline {
  readonly values = input.required<(number | null)[]>();
  readonly label = input('');

  protected readonly width = 120;
  protected readonly height = 32;

  protected readonly line = computed(() => {
    const vals = this.values();
    const known = vals.filter((v): v is number => v !== null);
    if (known.length < 2) return null;

    const min = Math.min(...known);
    const max = Math.max(...known);
    const range = max - min || 1;
    const step = this.width / (vals.length - 1);
    const pad = 3;

    const pts = vals
      .map((v, i) => (v === null ? null : {
        x: i * step,
        y: pad + (1 - (v - min) / range) * (this.height - 2 * pad),
      }))
      .filter((p): p is { x: number; y: number } => p !== null);

    return {
      points: pts.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '),
      last: pts[pts.length - 1],
    };
  });
}
