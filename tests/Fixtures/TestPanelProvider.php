<?php

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\PostResource;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('testing')
            ->login()
            ->resources([
                PostResource::class,
            ]);
    }
}
