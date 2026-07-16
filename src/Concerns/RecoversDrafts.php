<?php

namespace Oddvalue\FilamentDraftRecovery\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\DraftRecoveryPlugin;
use Oddvalue\FilamentDraftRecovery\Facades\DraftRecovery;

/**
 * Auto-saves drafts of the page's form state and offers to recover them when
 * the user returns. Add to a Filament CreateRecord or EditRecord page.
 *
 * Storage is driver-based: "local-storage" keeps drafts in the user's
 * browser; "database" and "laravel-drafts" persist them server-side. The
 * driver resolves from, in order: the page's $draftStore property, the
 * panel's DraftRecoveryPlugin::store(), the package config.
 *
 * IMPORTANT: a page that defines its own afterCreate()/afterSave() hook
 * silently overrides the ones declared here — such pages must call
 * $this->dispatchDraftRecoveryClear() from their own hook, or drafts will
 * never be cleared. Likewise for pages defining their own getFooter().
 *
 * @mixin CreateRecord|EditRecord
 *
 * @property ?string $draftStore
 */
trait RecoversDrafts
{
    public function getDraftStore(): DraftStore
    {
        return DraftRecovery::driver($this->getDraftStoreName());
    }

    public function getDraftStoreName(): ?string
    {
        if (property_exists($this, 'draftStore') && filled($this->draftStore)) {
            return $this->draftStore;
        }

        $panel = filament()->getCurrentOrDefaultPanel();

        if (! $panel?->hasPlugin(DraftRecoveryPlugin::ID)) {
            return null;
        }

        $plugin = $panel->getPlugin(DraftRecoveryPlugin::ID);

        return $plugin instanceof DraftRecoveryPlugin ? $plugin->getStore() : null;
    }

    public function draftRecoveryKey(): string
    {
        $segments = [
            filament()->auth()->id() ?? auth()->id() ?? 'guest',
            filament()->getCurrentOrDefaultPanel()?->getId() ?? 'default',
            static::getResource()::getSlug(),
            $this instanceof EditRecord ? 'edit' : 'create',
        ];

        if ($this instanceof EditRecord) {
            $segments[] = $this->getRecord()->getKey();
        }

        return config('filament-draft-recovery.key_prefix') . implode(':', $segments);
    }

    public function draftRecoveryContext(): DraftContext
    {
        return new DraftContext(
            key: $this->draftRecoveryKey(),
            modelClass: static::getResource()::getModel(),
            operation: $this instanceof EditRecord ? 'edit' : 'create',
            record: $this instanceof EditRecord ? $this->getRecord() : null,
            userId: filament()->auth()->id() ?? auth()->id(),
        );
    }

    /**
     * Livewire trait lifecycle hook — offers recovery of a server-side draft
     * once the form has been filled.
     */
    public function mountRecoversDrafts(): void
    {
        $store = $this->getDraftStore();

        if ($store->isClientSide()) {
            return;
        }

        $context = $this->draftRecoveryContext();
        $key = $context->key;
        $draft = $store->get($context);

        if (! $draft) {
            return;
        }

        $currentData = $this->stripDraftRecoveryExcludedFields($this->data ?? []);
        $draftData = $this->stripDraftRecoveryExcludedFields($draft->data);

        // Merging the draft over the current state changing nothing means the
        // draft holds nothing worth recovering (mirrors the client-side check).
        if ([...$currentData, ...$draftData] == $currentData) {
            $store->forget($context);

            return;
        }

        Notification::make('draft-recovery')
            ->title(__('filament-draft-recovery::draft-recovery.notification.title'))
            ->body(__('filament-draft-recovery::draft-recovery.notification.body', [
                'saved_at' => $draft->savedAt?->diffForHumans() ?? __('filament-draft-recovery::draft-recovery.notification.unknown_time'),
            ]))
            ->icon('heroicon-o-document-arrow-up')
            ->persistent()
            ->actions([
                Action::make('restore')
                    ->label(__('filament-draft-recovery::draft-recovery.notification.restore'))
                    ->button()
                    ->close()
                    ->dispatch('draft-recovery-restore', ['key' => $key]),
                Action::make('discard')
                    ->label(__('filament-draft-recovery::draft-recovery.notification.discard'))
                    ->color('gray')
                    ->close()
                    ->dispatch('draft-recovery-discard', ['key' => $key]),
            ])
            ->send();
    }

    /**
     * Called (debounced) by the JavaScript component when a server-side store
     * is active.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeRecoverableDraft(array $data): void
    {
        $store = $this->getDraftStore();

        if ($store->isClientSide()) {
            return;
        }

        $store->put($this->draftRecoveryContext(), $this->stripDraftRecoveryExcludedFields($data));
    }

    #[On('draft-recovery-restore')]
    public function restoreRecoverableDraft(string $key): void
    {
        $store = $this->getDraftStore();

        if ($store->isClientSide() || $key !== $this->draftRecoveryKey()) {
            return;
        }

        $draft = $store->get($this->draftRecoveryContext());

        if (! $draft) {
            return;
        }

        $this->form->fill([
            ...$this->data ?? [],
            ...$draft->data,
        ]);
    }

    #[On('draft-recovery-discard')]
    public function discardRecoverableDraft(string $key): void
    {
        $store = $this->getDraftStore();

        if ($store->isClientSide() || $key !== $this->draftRecoveryKey()) {
            return;
        }

        $store->forget($this->draftRecoveryContext());
    }

    protected function afterCreate(): void
    {
        $this->dispatchDraftRecoveryClear();
    }

    protected function afterSave(): void
    {
        $this->dispatchDraftRecoveryClear();
    }

    public function dispatchDraftRecoveryClear(): void
    {
        $context = $this->draftRecoveryContext();
        $store = $this->getDraftStore();

        if (! $store->isClientSide()) {
            $store->forget($context);
        }

        $this->dispatch('draft-recovery-clear', key: $context->key);
    }

    public function getFooter(): ?View
    {
        return view('filament-draft-recovery::draft-recovery', [
            'draftRecoveryConfig' => $this->getDraftRecoveryConfig(),
        ]);
    }

    /**
     * @return array{key: string, mode: string, prefix: string, expiryDays: int, excludedFields: array<string>}
     */
    public function getDraftRecoveryConfig(): array
    {
        return [
            'key' => $this->draftRecoveryKey(),
            'mode' => $this->getDraftStore()->isClientSide() ? 'client' : 'server',
            'prefix' => config('filament-draft-recovery.key_prefix'),
            'expiryDays' => $this->draftRecoveryExpiryDays(),
            'excludedFields' => $this->draftRecoveryExcludedFields(),
            'lang' => [
                'title' => __('filament-draft-recovery::draft-recovery.notification.title'),
                'body' => __('filament-draft-recovery::draft-recovery.notification.body'),
                'restore' => __('filament-draft-recovery::draft-recovery.notification.restore'),
                'discard' => __('filament-draft-recovery::draft-recovery.notification.discard'),
            ],
        ];
    }

    protected function draftRecoveryExpiryDays(): int
    {
        return (int) config('filament-draft-recovery.expiry_days', 7);
    }

    /**
     * Top-level form data keys that must never be persisted as a draft
     * (passwords, tokens, anything sensitive — local-storage drafts are
     * plaintext in the user's browser).
     *
     * @return array<string>
     */
    protected function draftRecoveryExcludedFields(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripDraftRecoveryExcludedFields(array $data): array
    {
        return array_diff_key($data, array_flip($this->draftRecoveryExcludedFields()));
    }
}
