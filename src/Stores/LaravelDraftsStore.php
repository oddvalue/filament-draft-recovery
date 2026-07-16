<?php

namespace Oddvalue\FilamentDraftRecovery\Stores;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;
use Oddvalue\LaravelDrafts\Concerns\HasDrafts;
use RuntimeException;

/**
 * Stores drafts through oddvalue/laravel-drafts, directly on the model being
 * edited — the page's model must use the HasDrafts trait.
 *
 * - Edit pages: auto-saves upsert a single "auto" draft row per record — an
 *   unpublished revision that is NOT flagged as current (the record keeps its
 *   is_current flag and any intentional current draft is untouched). It reads
 *   as the record's latest partial draft and is updated in place on every
 *   auto-save, so no revision churn.
 * - Create pages: the first auto-save creates an unpublished draft record
 *   (createDraft) which subsequent auto-saves update in place; recovery finds
 *   the user's latest unpublished draft of the model.
 *
 * The auto draft is recognised by the flag combination unpublished +
 * not-current + null published_at (ordinary revision copies of a published
 * record retain its published_at).
 *
 * Saves are best-effort: a payload that violates database constraints (e.g.
 * required columns not yet filled in) is skipped and retried on the next
 * auto-save.
 *
 * Note: clearing drafts deletes only the auto draft row (edit) or the user's
 * unpublished draft record (create) via query deletes, so the published
 * record, intentional drafts, and revision history are never touched.
 */
class LaravelDraftsStore implements DraftStore
{
    public function __construct(
        protected int $expiryDays = 7,
    ) {
        if (! trait_exists(HasDrafts::class)) {
            throw new RuntimeException(
                'The laravel-drafts draft store requires the oddvalue/laravel-drafts package. Install it with: composer require oddvalue/laravel-drafts'
            );
        }
    }

    public function isClientSide(): bool
    {
        return false;
    }

    public function get(DraftContext $context): ?RecoveredDraft
    {
        $this->assertDraftableModel($context);

        $draft = $this->resolveDraft($context);

        if (! $draft) {
            return null;
        }

        if ($draft->updated_at?->lt(now()->subDays($this->expiryDays))) {
            $this->forget($context);

            return null;
        }

        return new RecoveredDraft(
            data: $this->payloadFromDraft($draft),
            savedAt: $draft->updated_at,
        );
    }

    public function put(DraftContext $context, array $data): void
    {
        $this->assertDraftableModel($context);

        $attributes = $this->attributesFromPayload($context, $data);

        if ($attributes === []) {
            return;
        }

        try {
            if ($context->record) {
                $this->upsertAutoDraft($context->record, $attributes);

                return;
            }

            if ($existing = $this->resolveCreatePageDraft($context)) {
                $existing->withoutRevision()->fill($attributes)->save();

                return;
            }

            ($context->modelClass)::createDraft($attributes);
        } catch (QueryException) {
            // Best-effort: incomplete form state can violate column
            // constraints — the next auto-save will try again.
        }
    }

    public function forget(DraftContext $context): void
    {
        $this->assertDraftableModel($context);

        if ($context->record) {
            $autoDraft = $this->resolveAutoDraft($context->record);

            if ($autoDraft) {
                // Query delete on purpose: Eloquent deletes on a HasDrafts
                // model cascade to every revision sharing the uuid, including
                // the published row.
                $autoDraft->newModelQuery()->whereKey($autoDraft->getKey())->delete();
            }

            return;
        }

        $draft = $this->resolveCreatePageDraft($context);

        if ($draft) {
            $draft->newModelQuery()->whereKey($draft->getKey())->delete();
        }
    }

    protected function resolveDraft(DraftContext $context): ?Model
    {
        if ($context->record) {
            return $this->resolveAutoDraft($context->record);
        }

        return $this->resolveCreatePageDraft($context);
    }

    /**
     * The record's auto draft: an unpublished, not-current revision with a
     * null published_at. Saved quietly so laravel-drafts' model events never
     * promote it to the current draft or spawn revisions.
     */
    public function resolveAutoDraft(Model $record): ?Model
    {
        return $record->newQuery()
            ->withDrafts()
            ->where($record->getUuidColumn(), $record->{$record->getUuidColumn()})
            ->where($record->getIsPublishedColumn(), false)
            ->where($record->getIsCurrentColumn(), false)
            ->whereNull($record->getPublishedAtColumn())
            ->latest('updated_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertAutoDraft(Model $record, array $attributes): void
    {
        if ($existing = $this->resolveAutoDraft($record)) {
            $existing->forceFill($attributes);
            $existing->saveQuietly();

            return;
        }

        $autoDraft = $record->replicate();
        $autoDraft->forceFill([
            ...$attributes,
            $record->getIsCurrentColumn() => false,
            $record->getIsPublishedColumn() => false,
            $record->getPublishedAtColumn() => null,
        ]);
        $autoDraft->saveQuietly();
    }

    /**
     * The user's latest unpublished, current draft record of the model — a
     * draft created from a create page rather than a revision of an existing
     * record.
     */
    protected function resolveCreatePageDraft(DraftContext $context): ?Model
    {
        /** @var Model&HasDrafts $model */
        $model = new ($context->modelClass);

        return ($context->modelClass)::query()
            ->onlyDrafts()
            ->current()
            ->when(
                $context->userId !== null,
                fn (Builder $query) => $query->where($model->getPublisherColumns()['id'], $context->userId),
            )
            ->latest('updated_at')
            ->first();
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
                "The laravel-drafts draft store requires [{$context->modelClass}] to use the " . HasDrafts::class . ' trait.'
            );
        }
    }
}
