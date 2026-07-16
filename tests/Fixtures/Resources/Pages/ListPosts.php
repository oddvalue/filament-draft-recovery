<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\PostResource;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;
}
