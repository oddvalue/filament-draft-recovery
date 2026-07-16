<?php

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use Oddvalue\FilamentDraftRecovery\Concerns\RecoversDrafts;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\PostResource;

class EditPost extends EditRecord
{
    use RecoversDrafts;

    protected static string $resource = PostResource::class;
}
