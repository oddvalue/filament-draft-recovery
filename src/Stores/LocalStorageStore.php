<?php

namespace Oddvalue\FilamentDraftRecovery\Stores;

use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;

/**
 * Drafts live entirely in the user's browser localStorage — the JavaScript
 * component handles persistence, recovery, and clearing. Nothing reaches
 * the server, so the server-side accessors are inert.
 */
class LocalStorageStore implements DraftStore
{
    public function isClientSide(): bool
    {
        return true;
    }

    public function get(DraftContext $context): ?RecoveredDraft
    {
        return null;
    }

    public function put(DraftContext $context, array $data): void
    {
        //
    }

    public function forget(DraftContext $context): void
    {
        //
    }
}
