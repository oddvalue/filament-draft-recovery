<?php

namespace Oddvalue\FilamentDraftRecovery\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Contracts\ResolvesCreatePageStore;
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
    /**
     * Sentinel for a drafted upload whose temporary file no longer exists.
     * NUL-prefixed so it can never collide with real form data.
     */
    private const STALE_DRAFT_UPLOAD = "\0filament-draft-recovery:stale-upload";

    public function getDraftStore(): DraftStore
    {
        $store = DraftRecovery::driver($this->getDraftStoreName());

        // Some stores only persist drafts of existing records and delegate
        // create pages elsewhere (e.g. laravel-drafts auto drafts).
        if (! $this instanceof EditRecord && $store instanceof ResolvesCreatePageStore) {
            return $store->getCreatePageStore();
        }

        return $store;
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
        $draftData = $this->resolveDraftRecoveryUploads($this->stripDraftRecoveryExcludedFields($draft->data));

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

        $draftData = $this->resolveDraftRecoveryUploads($draft->data);

        $pendingUploads = array_filter($draftData, fn (mixed $value): bool => $this->holdsPendingUpload($value));

        $this->form->fill([
            ...$this->data ?? [],
            ...array_diff_key($draftData, $pendingUploads),
        ]);

        // Filling a file upload field treats every state entry as a stored
        // file path and checks it against the component's disk, which would
        // discard pending uploads — their state is re-applied directly.
        foreach ($pendingUploads as $field => $value) {
            data_set($this->data, $field, $value);
        }
    }

    /**
     * Checked against the storage disk rather than via
     * TemporaryUploadedFile::exists() — constructing a TemporaryUploadedFile
     * touches its path into existence in test environments, which would make
     * every dead marker look alive.
     */
    protected function pendingUploadExists(string $filename): bool
    {
        return FileUploadConfiguration::storage()->exists(FileUploadConfiguration::path($filename));
    }

    protected function holdsPendingUpload(mixed $value): bool
    {
        if ($value instanceof TemporaryUploadedFile) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->holdsPendingUpload($item)) {
                return true;
            }
        }

        return false;
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

    /**
     * Server-side stores persist pending (not yet saved) file uploads as
     * Livewire temporary upload markers. The temporary file a marker points
     * to is short-lived — Livewire prunes its temporary upload directory
     * independently of the draft's expiry — so markers are re-checked at
     * recovery time: live ones are rehydrated into TemporaryUploadedFile
     * instances, dead ones are dropped together with any upload state left
     * empty by the removal, letting the rest of the draft recover cleanly.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveDraftRecoveryUploads(array $data): array
    {
        $resolved = $this->resolveDraftRecoveryUploadValue($data);

        return is_array($resolved) ? $resolved : [];
    }

    protected function resolveDraftRecoveryUploadValue(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, 'livewire-file:')) {
            $filename = substr($value, strlen('livewire-file:'));

            return $this->pendingUploadExists($filename)
                ? TemporaryUploadedFile::createFromLivewire($filename)
                : self::STALE_DRAFT_UPLOAD;
        }

        if (is_string($value) && str_starts_with($value, 'livewire-files:')) {
            $files = array_map(
                fn (string $filename): TemporaryUploadedFile => TemporaryUploadedFile::createFromLivewire($filename),
                array_values(array_filter(
                    (array) json_decode(substr($value, strlen('livewire-files:')), true),
                    fn ($filename): bool => is_string($filename) && $this->pendingUploadExists($filename),
                )),
            );

            return $files === [] ? self::STALE_DRAFT_UPLOAD : $files;
        }

        if (! is_array($value) || $value === []) {
            return $value;
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            $item = $this->resolveDraftRecoveryUploadValue($item);

            if ($item !== self::STALE_DRAFT_UPLOAD) {
                $resolved[$key] = $item;
            }
        }

        // Upload state left empty by dead markers is dropped entirely so the
        // form's current value survives the recovery merge.
        return $resolved === [] ? self::STALE_DRAFT_UPLOAD : $resolved;
    }
}
