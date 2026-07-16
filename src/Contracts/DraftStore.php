<?php

namespace Oddvalue\FilamentDraftRecovery\Contracts;

use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;

interface DraftStore
{
    /**
     * Client-side stores persist drafts in the browser; the server never
     * receives draft payloads and the get/put/forget methods are unused.
     */
    public function isClientSide(): bool;

    public function get(string $key): ?RecoveredDraft;

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $key, array $data): void;

    public function forget(string $key): void;
}
