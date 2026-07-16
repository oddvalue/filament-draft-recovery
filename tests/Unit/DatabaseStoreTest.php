<?php

use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Models\RecoverableDraft;
use Oddvalue\FilamentDraftRecovery\Stores\DatabaseStore;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models\Post;

function databaseContext(string $key): DraftContext
{
    return new DraftContext(
        key: $key,
        modelClass: Post::class,
        operation: 'create',
    );
}

it('stores and retrieves a draft payload', function (): void {
    $store = new DatabaseStore;

    $store->put(databaseContext('a-key'), ['title' => 'Hello']);

    $draft = $store->get(databaseContext('a-key'));

    expect($draft)->not->toBeNull()
        ->and($draft->data)->toBe(['title' => 'Hello'])
        ->and($draft->savedAt)->not->toBeNull();
});

it('overwrites the draft for the same key', function (): void {
    $store = new DatabaseStore;

    $store->put(databaseContext('a-key'), ['title' => 'First']);
    $store->put(databaseContext('a-key'), ['title' => 'Second']);

    expect($store->get(databaseContext('a-key'))->data)->toBe(['title' => 'Second'])
        ->and(RecoverableDraft::query()->where('key', 'a-key')->count())->toBe(1);
});

it('returns null for a missing draft', function (): void {
    expect((new DatabaseStore)->get(databaseContext('missing')))->toBeNull();
});

it('forgets a draft', function (): void {
    $store = new DatabaseStore;

    $store->put(databaseContext('a-key'), ['title' => 'Hello']);
    $store->forget(databaseContext('a-key'));

    expect($store->get(databaseContext('a-key')))->toBeNull();
});

it('ignores and prunes expired drafts', function (): void {
    $store = new DatabaseStore(expiryDays: 7);

    $store->put(databaseContext('a-key'), ['title' => 'Old']);

    RecoverableDraft::query()->where('key', 'a-key')->update([
        'updated_at' => now()->subDays(8),
    ]);

    expect($store->get(databaseContext('a-key')))->toBeNull()
        ->and(RecoverableDraft::query()->where('key', 'a-key')->exists())->toBeFalse();
});

it('keys drafts independently', function (): void {
    $store = new DatabaseStore;

    $store->put(databaseContext('key-one'), ['title' => 'One']);
    $store->put(databaseContext('key-two'), ['title' => 'Two']);
    $store->forget(databaseContext('key-one'));

    expect($store->get(databaseContext('key-one')))->toBeNull()
        ->and($store->get(databaseContext('key-two'))->data)->toBe(['title' => 'Two']);
});
