<?php

use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Stores\LocalStorageStore;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models\Post;

it('is client side and inert on the server', function (): void {
    $store = new LocalStorageStore;

    $context = new DraftContext(
        key: 'a-key',
        modelClass: Post::class,
        operation: 'create',
    );

    $store->put($context, ['title' => 'Hello']);

    expect($store->isClientSide())->toBeTrue()
        ->and($store->get($context))->toBeNull();

    $store->forget($context);

    expect($store->get($context))->toBeNull();
});
