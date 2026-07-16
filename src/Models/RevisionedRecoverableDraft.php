<?php

namespace Oddvalue\FilamentDraftRecovery\Models;

use Oddvalue\LaravelDrafts\Concerns\HasDrafts;

/**
 * RecoverableDraft variant for the laravel-drafts store: each auto-save is
 * recorded as a revision. Requires the drafts columns on the table (see the
 * add_drafts_columns_to_recoverable_drafts_table migration).
 */
class RevisionedRecoverableDraft extends RecoverableDraft
{
    use HasDrafts;
}
