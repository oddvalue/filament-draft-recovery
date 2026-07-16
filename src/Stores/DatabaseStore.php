<?php

namespace Oddvalue\FilamentDraftRecovery\Stores;

use Illuminate\Database\Eloquent\Builder;
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
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

    public function get(DraftContext $context): ?RecoveredDraft
    {
        $draft = $this->query()->where('key', $context->key)->first();

        if (! $draft) {
            return null;
        }

        if ($draft->updated_at?->lt(now()->subDays($this->expiryDays))) {
            $this->forget($context);

            return null;
        }

        return new RecoveredDraft(
            data: $draft->payload ?? [],
            savedAt: $draft->updated_at,
        );
    }

    public function put(DraftContext $context, array $data): void
    {
        $this->query()->updateOrCreate(
            ['key' => $context->key],
            ['payload' => $data],
        );
    }

    public function forget(DraftContext $context): void
    {
        $this->query()->where('key', $context->key)->delete();
    }

    /**
     * @return Builder<RecoverableDraft>
     */
    protected function query(): Builder
    {
        return ($this->modelClass)::query();
    }
}
