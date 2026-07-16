<?php

use Oddvalue\FilamentDraftRecovery\Models\RecoverableDraft;
use Oddvalue\FilamentDraftRecovery\Stores\DatabaseStore;

it('stores and retrieves a draft payload', function (): void {
    $store = new DatabaseStore;

    $store->put('a-key', ['title' => 'Hello']);

    $draft = $store->get('a-key');

    expect($draft)->not->toBeNull()
        ->and($draft->data)->toBe(['title' => 'Hello'])
        ->and($draft->savedAt)->not->toBeNull();
});

it('overwrites the draft for the same key', function (): void {
    $store = new DatabaseStore;

    $store->put('a-key', ['title' => 'First']);
    $store->put('a-key', ['title' => 'Second']);

    expect($store->get('a-key')->data)->toBe(['title' => 'Second'])
        ->and(RecoverableDraft::query()->where('key', 'a-key')->count())->toBe(1);
});

it('returns null for a missing draft', function (): void {
    expect((new DatabaseStore)->get('missing'))->toBeNull();
});

it('forgets a draft', function (): void {
    $store = new DatabaseStore;

    $store->put('a-key', ['title' => 'Hello']);
    $store->forget('a-key');

    expect($store->get('a-key'))->toBeNull();
});

it('ignores and prunes expired drafts', function (): void {
    $store = new DatabaseStore(expiryDays: 7);

    $store->put('a-key', ['title' => 'Old']);

    RecoverableDraft::query()->where('key', 'a-key')->update([
        'updated_at' => now()->subDays(8),
    ]);

    expect($store->get('a-key'))->toBeNull()
        ->and(RecoverableDraft::query()->where('key', 'a-key')->exists())->toBeFalse();
});

it('keys drafts independently', function (): void {
    $store = new DatabaseStore;

    $store->put('key-one', ['title' => 'One']);
    $store->put('key-two', ['title' => 'Two']);
    $store->forget('key-one');

    expect($store->get('key-one'))->toBeNull()
        ->and($store->get('key-two')->data)->toBe(['title' => 'Two']);
});
