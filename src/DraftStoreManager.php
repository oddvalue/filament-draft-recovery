<?php

namespace Oddvalue\FilamentDraftRecovery;

use Illuminate\Support\Manager;
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
        return $this->config->get('filament-draft-recovery.store', 'local-storage');
    }

    public function createLocalStorageDriver(): LocalStorageStore
    {
        return new LocalStorageStore;
    }

    public function createDatabaseDriver(): DatabaseStore
    {
        return new DatabaseStore(
            modelClass: $this->config->get('filament-draft-recovery.database.model'),
            expiryDays: (int) $this->config->get('filament-draft-recovery.expiry_days', 7),
        );
    }

    public function createLaravelDraftsDriver(): LaravelDraftsStore
    {
        return new LaravelDraftsStore(
            modelClass: $this->config->get('filament-draft-recovery.laravel-drafts.model'),
            expiryDays: (int) $this->config->get('filament-draft-recovery.expiry_days', 7),
        );
    }
}
