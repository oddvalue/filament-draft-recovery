<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Contracts;

/**
 * Implemented by stores that can only persist drafts of existing records
 * (edit pages) and therefore delegate create-page drafts to another store.
 */
interface ResolvesCreatePageStore
{
    public function getCreatePageStore(): DraftStore;
}
