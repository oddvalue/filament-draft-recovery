<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use Oddvalue\FilamentDraftRecovery\Concerns\RecoversDrafts;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\PostResource;

/**
 * Clears the draft from its own afterSave(), as pages had to before Filament
 * supported trait-named hooks. The clear must still only run once.
 */
class EditPostWithLegacySaveHook extends EditRecord
{
    use RecoversDrafts;

    protected static string $resource = PostResource::class;

    protected function afterSave(): void
    {
        $this->dispatchDraftRecoveryClear();
    }
}
