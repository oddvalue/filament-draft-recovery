<?php

use Filament\Facades\Filament;
use Oddvalue\FilamentDraftRecovery\DraftRecoveryPlugin;

it('exposes its id', function (): void {
    expect(DraftRecoveryPlugin::make()->getId())->toBe('filament-draft-recovery');
});

it('holds a per panel store choice', function (): void {
    $plugin = DraftRecoveryPlugin::make()->store('database');

    expect($plugin->getStore())->toBe('database');
});

it('defaults to no store override', function (): void {
    expect(DraftRecoveryPlugin::make()->getStore())->toBeNull();
});

it('registers and boots on a panel without side effects', function (): void {
    $panel = Filament::getPanel('testing');
    $plugin = DraftRecoveryPlugin::make();

    $plugin->register($panel);
    $plugin->boot($panel);

    expect($plugin->getId())->toBe('filament-draft-recovery');
});

it('is retrievable from the current panel', function (): void {
    $plugin = DraftRecoveryPlugin::make()->store('database');

    Filament::getPanel('testing')->plugin($plugin);

    expect(DraftRecoveryPlugin::get())->toBe($plugin);
});
