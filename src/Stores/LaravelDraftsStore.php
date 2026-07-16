<?php

namespace Oddvalue\FilamentDraftRecovery\Stores;

use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;
use Oddvalue\FilamentDraftRecovery\Models\RevisionedRecoverableDraft;
use Oddvalue\LaravelDrafts\Concerns\HasDrafts;
use RuntimeException;

/**
 * Persists draft payloads through oddvalue/laravel-drafts, so every auto-save
 * becomes a revision of the draft record (with the package's revision
 * retention applying). Requires the drafts columns on the
 * recoverable_drafts table — see the add_drafts_columns migration.
 */
class LaravelDraftsStore implements DraftStore
{
    /**
     * @param  class-string<RevisionedRecoverableDraft>|null  $modelClass
     */
    public function __construct(
        protected ?string $modelClass = null,
        protected int $expiryDays = 7,
    ) {
        if (! trait_exists(HasDrafts::class)) {
            throw new RuntimeException(
                'The laravel-drafts draft store requires the oddvalue/laravel-drafts package. Install it with: composer require oddvalue/laravel-drafts'
            );
        }

        $this->modelClass ??= RevisionedRecoverableDraft::class;
    }

    public function isClientSide(): bool
    {
        return false;
    }

    public function get(string $key): ?RecoveredDraft
    {
        $draft = $this->currentDraft($key);

        if (! $draft) {
            return null;
        }

        if ($draft->updated_at?->lt(now()->subDays($this->expiryDays))) {
            $this->forget($key);

            return null;
        }

        return new RecoveredDraft(
            data: $draft->payload ?? [],
            savedAt: $draft->updated_at,
        );
    }

    public function put(string $key, array $data): void
    {
        $draft = $this->currentDraft($key);

        if ($draft) {
            $draft->updateAsDraft(['payload' => $data]);

            return;
        }

        ($this->modelClass)::createDraft([
            'key' => $key,
            'payload' => $data,
        ]);
    }

    public function forget(string $key): void
    {
        ($this->modelClass)::query()
            ->withDrafts()
            ->where('key', $key)
            ->get()
            ->each->delete();
    }

    protected function currentDraft(string $key): ?RevisionedRecoverableDraft
    {
        return ($this->modelClass)::query()
            ->withDrafts()
            ->where('key', $key)
            ->current()
            ->first();
    }
}
