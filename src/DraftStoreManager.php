<?php

namespace Oddvalue\FilamentDraftRecovery;

use Illuminate\Support\Manager;
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Models\RecoverableDraft;
use Oddvalue\FilamentDraftRecovery\Stores\DatabaseStore;
use Oddvalue\FilamentDraftRecovery\Stores\LaravelDraftsStore;
use Oddvalue\FilamentDraftRecovery\Stores\LocalStorageStore;

/**
 * @method \Oddvalue\FilamentDraftRecovery\Contracts\DraftStore driver(?string $driver = null)
 */
class DraftStoreManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $store = $this->config->get('filament-draft-recovery.store', 'local-storage');

        return is_string($store) ? $store : 'local-storage';
    }

    public function createLocalStorageDriver(): LocalStorageStore
    {
        return new LocalStorageStore;
    }

    public function createDatabaseDriver(): DatabaseStore
    {
        /** @var class-string<RecoverableDraft>|null $modelClass */
        $modelClass = $this->config->get('filament-draft-recovery.database.model');

        return new DatabaseStore(
            modelClass: $modelClass,
            expiryDays: $this->expiryDays(),
        );
    }

    public function createLaravelDraftsDriver(): LaravelDraftsStore
    {
        return new LaravelDraftsStore(
            expiryDays: $this->expiryDays(),
            createPageStore: fn (): DraftStore => $this->driver($this->laravelDraftsCreatePageStoreName()),
        );
    }

    /**
     * The store handling create-page drafts for the laravel-drafts store:
     * the configured create_store, falling back to the default store — or
     * "database" when the default is laravel-drafts itself.
     */
    protected function laravelDraftsCreatePageStoreName(): string
    {
        $configured = $this->config->get('filament-draft-recovery.laravel-drafts.create_store');

        $name = is_string($configured) && $configured !== '' ? $configured : $this->getDefaultDriver();

        return $name === 'laravel-drafts' ? 'database' : $name;
    }

    protected function expiryDays(): int
    {
        $expiryDays = $this->config->get('filament-draft-recovery.expiry_days', 7);

        return is_numeric($expiryDays) ? (int) $expiryDays : 7;
    }
}
