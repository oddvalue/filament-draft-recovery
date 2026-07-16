<?php

namespace Oddvalue\FilamentDraftRecovery\Contracts;

use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;

/**
 * Implement this interface (and register the implementation with
 * DraftRecovery::extend()) to supply a custom draft storage backend.
 */
interface DraftStore
{
    /**
     * Client-side stores persist drafts in the browser; the server never
     * receives draft payloads and the get/put/forget methods are unused.
     */
    public function isClientSide(): bool;

    public function get(DraftContext $context): ?RecoveredDraft;

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(DraftContext $context, array $data): void;

    public function forget(DraftContext $context): void;
}
