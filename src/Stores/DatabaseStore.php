<?php

namespace Oddvalue\FilamentDraftRecovery\Stores;

use Illuminate\Database\Eloquent\Builder;
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;
use Oddvalue\FilamentDraftRecovery\Models\RecoverableDraft;

class DatabaseStore implements DraftStore
{
    /**
     * @param  class-string<RecoverableDraft>|null  $modelClass
     */
    public function __construct(
        protected ?string $modelClass = null,
        protected int $expiryDays = 7,
    ) {
        $this->modelClass ??= RecoverableDraft::class;
    }

    public function isClientSide(): bool
    {
        return false;
    }

    public function get(string $key): ?RecoveredDraft
    {
        $draft = $this->query()->where('key', $key)->first();

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
        $this->query()->updateOrCreate(
            ['key' => $key],
            ['payload' => $data],
        );
    }

    public function forget(string $key): void
    {
        $this->query()->where('key', $key)->delete();
    }

    /**
     * @return Builder<RecoverableDraft>
     */
    protected function query(): Builder
    {
        return ($this->modelClass)::query();
    }
}
