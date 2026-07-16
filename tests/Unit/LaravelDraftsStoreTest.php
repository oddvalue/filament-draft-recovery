<?php

use Oddvalue\FilamentDraftRecovery\Models\RevisionedRecoverableDraft;
use Oddvalue\FilamentDraftRecovery\Stores\LaravelDraftsStore;

it('stores and retrieves a draft payload', function (): void {
    $store = new LaravelDraftsStore;

    $store->put('a-key', ['title' => 'Hello']);

    $draft = $store->get('a-key');

    expect($draft)->not->toBeNull()
        ->and($draft->data)->toBe(['title' => 'Hello']);
});

it('records revisions of subsequent saves', function (): void {
    $store = new LaravelDraftsStore;

    $store->put('a-key', ['title' => 'First']);
    $store->put('a-key', ['title' => 'Second']);

    expect($store->get('a-key')->data)->toBe(['title' => 'Second'])
        ->and(
            RevisionedRecoverableDraft::query()->withDrafts()->where('key', 'a-key')->count()
        )->toBeGreaterThan(1);
});

it('forgets every revision of a draft', function (): void {
    $store = new LaravelDraftsStore;

    $store->put('a-key', ['title' => 'First']);
    $store->put('a-key', ['title' => 'Second']);
    $store->forget('a-key');

    expect($store->get('a-key'))->toBeNull()
        ->and(
            RevisionedRecoverableDraft::query()->withDrafts()->where('key', 'a-key')->exists()
        )->toBeFalse();
});

it('ignores and prunes expired drafts', function (): void {
    $store = new LaravelDraftsStore(expiryDays: 7);

    $store->put('a-key', ['title' => 'Old']);

    RevisionedRecoverableDraft::query()->withDrafts()->where('key', 'a-key')->update([
        'updated_at' => now()->subDays(8),
    ]);

    expect($store->get('a-key'))->toBeNull();
});
