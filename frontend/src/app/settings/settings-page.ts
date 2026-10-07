import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { AppSettings, Tag, VersionInfo } from '../core/models';

@Component({
  selector: 'app-settings-page',
  imports: [FormsModule],
  templateUrl: './settings-page.html',
  styleUrl: './settings-page.scss',
})
export class SettingsPage implements OnInit {
  private api = inject(ApiService);

  protected readonly settings = signal<AppSettings | null>(null);
  protected readonly key = signal('');
  protected readonly model = signal('');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly notice = signal<string | null>(null);

  protected readonly version = signal<VersionInfo | null>(null);
  protected readonly tags = signal<Tag[]>([]);
  protected readonly tagName = signal('');
  protected readonly tagError = signal<string | null>(null);
  protected readonly tagNotice = signal<string | null>(null);
  protected readonly activeTags = computed(() => this.tags().filter(t => !t.archived));
  protected readonly hiddenTags = computed(() => this.tags().filter(t => t.archived));

  async ngOnInit() {
    await Promise.all([
      this.run(async () => this.apply(await this.api.getSettings())),
      this.runTags(() => this.reloadTags()),
      this.api.getVersion().then(v => this.version.set(v)).catch(() => undefined),
    ]);
  }

  addTag() {
    const name = this.tagName().trim();
    if (!name) return;
    this.runTags(async () => {
      await this.api.createTag(name);
      this.tagName.set('');
      await this.reloadTags();
    });
  }

  removeTag(tag: Tag) {
    this.runTags(async () => {
      const res = await this.api.deleteTag(tag.id);
      if (res.result === 'archived') {
        this.tagNotice.set(`${tag.name} is hidden. Days that have it keep it.`);
      }
      await this.reloadTags();
    });
  }

  restoreTag(tag: Tag) {
    this.runTags(async () => {
      await this.api.restoreTag(tag.id);
      await this.reloadTags();
    });
  }

  private async reloadTags() {
    this.tags.set(await this.api.getTags(true));
  }

  private async runTags(action: () => Promise<void>) {
    this.tagError.set(null);
    this.tagNotice.set(null);
    try {
      await action();
    } catch (e) {
      this.tagError.set((e as Error).message);
    }
  }

  saveKey() {
    const key = this.key().trim();
    if (!key) return;
    this.run(async () => {
      this.apply(await this.api.saveSettings({ gemini_api_key: key }));
      this.key.set('');
      this.notice.set('Key saved. You can analyze days now.');
    });
  }

  removeKey() {
    this.run(async () => {
      this.apply(await this.api.saveSettings({ gemini_api_key: '' }));
      this.notice.set('Key removed.');
    });
  }

  saveModel() {
    this.run(async () => {
      this.apply(await this.api.saveSettings({ gemini_model: this.model().trim() }));
      this.notice.set('Model saved.');
    });
  }

  private apply(s: AppSettings) {
    this.settings.set(s);
    this.model.set(s.gemini_model);
  }

  private async run(action: () => Promise<void>) {
    this.busy.set(true);
    this.error.set(null);
    this.notice.set(null);
    try {
      await action();
    } catch (e) {
      this.error.set((e as Error).message);
    } finally {
      this.busy.set(false);
    }
  }
}
