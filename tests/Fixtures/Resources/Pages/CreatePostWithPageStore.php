<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use Oddvalue\FilamentDraftRecovery\Concerns\RecoversDrafts;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\PostResource;

class CreatePostWithPageStore extends CreateRecord
{
    use RecoversDrafts;

    protected static string $resource = PostResource::class;

    protected ?string $draftStore = 'database';
}
