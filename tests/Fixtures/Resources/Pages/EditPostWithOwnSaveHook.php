<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use Oddvalue\FilamentDraftRecovery\Concerns\RecoversDrafts;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\PostResource;

/**
 * Defines its own afterSave() without calling the trait's clear method —
 * the trait-named hook must still run alongside it.
 */
class EditPostWithOwnSaveHook extends EditRecord
{
    use RecoversDrafts;

    protected static string $resource = PostResource::class;

    public bool $ownHookCalled = false;

    protected function afterSave(): void
    {
        $this->ownHookCalled = true;
    }
}
