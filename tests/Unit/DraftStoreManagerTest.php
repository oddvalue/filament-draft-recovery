<?php

use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;
use Oddvalue\FilamentDraftRecovery\Facades\DraftRecovery;
use Oddvalue\FilamentDraftRecovery\Stores\DatabaseStore;
use Oddvalue\FilamentDraftRecovery\Stores\LaravelDraftsStore;
use Oddvalue\FilamentDraftRecovery\Stores\LocalStorageStore;

it('defaults to the local-storage store', function (): void {
    expect(DraftRecovery::driver())->toBeInstanceOf(LocalStorageStore::class);
});

it('respects the configured default store', function (): void {
    config()->set('filament-draft-recovery.store', 'database');

    expect(DraftRecovery::driver())->toBeInstanceOf(DatabaseStore::class);
});

it('resolves each built in store by name', function (string $name, string $class): void {
    expect(DraftRecovery::driver($name))->toBeInstanceOf($class);
})->with([
    ['local-storage', LocalStorageStore::class],
    ['database', DatabaseStore::class],
    ['laravel-drafts', LaravelDraftsStore::class],
]);

it('supports custom stores via extend', function (): void {
    $custom = new class implements DraftStore
    {
        public function isClientSide(): bool
        {
            return false;
        }

        public function get(string $key): ?RecoveredDraft
        {
            return null;
        }

        public function put(string $key, array $data): void {}

        public function forget(string $key): void {}
    };

    DraftRecovery::extend('custom', fn () => $custom);

    expect(DraftRecovery::driver('custom'))->toBe($custom);
});

it('marks only the local-storage store as client side', function (): void {
    expect(DraftRecovery::driver('local-storage')->isClientSide())->toBeTrue()
        ->and(DraftRecovery::driver('database')->isClientSide())->toBeFalse()
        ->and(DraftRecovery::driver('laravel-drafts')->isClientSide())->toBeFalse();
});
