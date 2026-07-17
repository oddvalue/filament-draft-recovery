<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Stores;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Contracts\ResolvesCreatePageStore;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;
use Oddvalue\LaravelDrafts\Concerns\HasDrafts;
use RuntimeException;

/**
 * Stores edit-page drafts through oddvalue/laravel-drafts' first-class auto
 * draft feature, directly on the model being edited — the page's model must
 * use the HasDrafts trait and auto drafts must be enabled
 * (drafts.auto_drafts.enabled).
 *
 * Each auto-save calls saveAsAutoDraft() on the record: a single, quietly
 * upserted working copy that is never the current draft, never spawns
 * revisions, and reads back via the record's autoDraft() relation.
 *
 * Auto drafts only exist for existing records, so create-page drafts are
 * delegated to another store (the "create page store"): the
 * filament-draft-recovery.laravel-drafts.create_store config value, falling
 * back to the default store (or "database" when the default is this store).
 *
 * Saves are best-effort: a payload that violates database constraints (e.g.
 * required columns not yet filled in) is skipped and retried on the next
 * auto-save.
 */
class LaravelDraftsStore implements DraftStore, ResolvesCreatePageStore
{
    /**
     * @param  (Closure(): DraftStore)|null  $createPageStore
     */
    public function __construct(
        protected int $expiryDays = 7,
        protected ?Closure $createPageStore = null,
    ) {
        if (! trait_exists(HasDrafts::class)) {
            // Unreachable when the suggested dependency is installed, as it
            // always is in the test suite.
            // @codeCoverageIgnoreStart
            throw new RuntimeException(
                'The laravel-drafts draft store requires the oddvalue/laravel-drafts package. Install it with: composer require oddvalue/laravel-drafts'
            );
            // @codeCoverageIgnoreEnd
        }
    }

    public function isClientSide(): bool
    {
        return false;
    }

    /**
     * The store handling create-page drafts, since auto drafts require an
     * existing record.
     */
    public function getCreatePageStore(): DraftStore
    {
        $store = $this->createPageStore instanceof Closure ? ($this->createPageStore)() : new DatabaseStore(expiryDays: $this->expiryDays);

        if ($store instanceof self) {
            throw new RuntimeException(
                'The laravel-drafts draft store cannot delegate create page drafts to itself — configure filament-draft-recovery.laravel-drafts.create_store with a different store.'
            );
        }

        return $store;
    }

    public function get(DraftContext $context): ?RecoveredDraft
    {
        if (! $context->record instanceof Model) {
            return $this->getCreatePageStore()->get($context);
        }

        $this->assertDraftableModel($context);

        $autoDraft = $this->resolveAutoDraft($context->record);

        if (! $autoDraft instanceof Model) {
            return null;
        }

        if ($autoDraft->updated_at?->lt(now()->subDays($this->expiryDays))) {
            $this->forget($context);

            return null;
        }

        return new RecoveredDraft(
            data: $this->payloadFromDraft($autoDraft),
            savedAt: $autoDraft->updated_at,
        );
    }

    public function put(DraftContext $context, array $data): void
    {
        if (! $context->record instanceof Model) {
            $this->getCreatePageStore()->put($context, $data);

            return;
        }

        $this->assertDraftableModel($context);

        $attributes = $this->attributesFromPayload($context, $data);

        if ($attributes === []) {
            return;
        }

        try {
            $context->record->saveAsAutoDraft($attributes);
        } catch (QueryException) {
            // Best-effort: incomplete form state can violate column
            // constraints — the next auto-save will try again.
        }
    }

    public function forget(DraftContext $context): void
    {
        if (! $context->record instanceof Model) {
            $this->getCreatePageStore()->forget($context);

            return;
        }

        $this->assertDraftableModel($context);

        $context->record->discardAutoDraft();
    }

    /**
     * The record's auto draft, as maintained by laravel-drafts.
     */
    public function resolveAutoDraft(Model $record): ?Model
    {
        return $record->autoDraft()->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributesFromPayload(DraftContext $context, array $data): array
    {
        return array_intersect_key($data, array_flip($this->draftableColumns($context)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadFromDraft(Model $draft): array
    {
        $context = new DraftContext(
            key: '',
            modelClass: $draft::class,
            operation: 'edit',
        );

        return array_intersect_key(
            $draft->attributesToArray(),
            array_flip($this->draftableColumns($context)),
        );
    }

    /**
     * The model's real columns minus its primary key, timestamps, and the
     * laravel-drafts bookkeeping columns — the attributes a form draft may
     * carry.
     *
     * @return array<string>
     */
    protected function draftableColumns(DraftContext $context): array
    {
        /** @var Model&HasDrafts $model */
        $model = new ($context->modelClass);

        $excluded = [
            $model->getKeyName(),
            $model->getCreatedAtColumn(),
            $model->getUpdatedAtColumn(),
            $model->getUuidColumn(),
            $model->getIsCurrentColumn(),
            $model->getIsPublishedColumn(),
            $model->getIsAutoColumn(),
            $model->getPublishedAtColumn(),
            ...array_values($model->getPublisherColumns()),
        ];

        return array_values(array_diff(
            Schema::getColumnListing($model->getTable()),
            $excluded,
        ));
    }

    protected function assertDraftableModel(DraftContext $context): void
    {
        if (! in_array(HasDrafts::class, class_uses_recursive($context->modelClass))) {
            throw new RuntimeException(
                sprintf('The laravel-drafts draft store requires [%s] to use the ', $context->modelClass) . HasDrafts::class . ' trait.'
            );
        }

        if (! ($context->modelClass)::autoDraftsEnabled()) {
            throw new RuntimeException(
                'The laravel-drafts draft store requires auto drafts to be enabled — set the drafts.auto_drafts.enabled config option to true.'
            );
        }
    }
}
