<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Facades\DraftRecovery;
use Oddvalue\FilamentDraftRecovery\Stores\DatabaseStore;
use Oddvalue\FilamentDraftRecovery\Stores\LaravelDraftsStore;
use Oddvalue\FilamentDraftRecovery\Stores\LocalStorageStore;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models\Post;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models\User;

function editContext(Model $record): DraftContext
{
    return new DraftContext(
        key: 'edit-key',
        modelClass: $record::class,
        operation: 'edit',
        record: $record,
        userId: auth()->id(),
    );
}

function createContext(): DraftContext
{
    return new DraftContext(
        key: 'create-key',
        modelClass: Post::class,
        operation: 'create',
        userId: auth()->id(),
    );
}

it('rejects models that do not use HasDrafts', function (): void {
    $user = User::query()->create([
        'name' => 'Test',
        'email' => 'reject@example.com',
        'password' => 'secret',
    ]);

    (new LaravelDraftsStore)->get(new DraftContext(
        key: 'a-key',
        modelClass: User::class,
        operation: 'edit',
        record: $user,
    ));
})->throws(RuntimeException::class, 'HasDrafts');

it('rejects models when auto drafts are disabled', function (): void {
    config()->set('drafts.auto_drafts.enabled', false);

    $post = Post::query()->create(['title' => 'Published title']);

    (new LaravelDraftsStore)->get(editContext($post));
})->throws(RuntimeException::class, 'auto drafts to be enabled');

describe('edit pages', function (): void {
    it('stores the draft as the record auto draft', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title']);

        $post->refresh();
        $autoDraft = $store->resolveAutoDraft($post);

        expect($post->title)->toBe('Published title')
            ->and($post->is_current)->toBeTrue()
            ->and($autoDraft)->not->toBeNull()
            ->and($autoDraft->title)->toBe('Drafted title')
            ->and($autoDraft->is_auto)->toBeTrue()
            ->and($autoDraft->is_current)->toBeFalse()
            // The auto draft must not read as the record's current draft.
            ->and($post->drafts()->count())->toBe(0);
    });

    it('upserts the same auto draft row on subsequent saves', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'First']);

        $firstId = $store->resolveAutoDraft($post)->getKey();

        $store->put(editContext($post), ['title' => 'Second']);

        expect($store->resolveAutoDraft($post)->getKey())->toBe($firstId)
            ->and($store->get(editContext($post))->data['title'])->toBe('Second')
            ->and(Post::query()->withDrafts()->where('uuid', $post->uuid)->count())->toBe(2);
    });

    it('leaves an intentional current draft untouched', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        (clone $post)->updateAsDraft(['title' => 'Manual draft']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Auto draft']);
        $store->forget(editContext($post));

        $post->refresh();

        expect($post->drafts()->first()?->title)->toBe('Manual draft')
            ->and($store->resolveAutoDraft($post))->toBeNull();
    });

    it('retrieves the draft payload from the auto draft', function (): void {
        $post = Post::query()->create(['title' => 'Published title', 'body' => 'Body']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title']);

        $draft = $store->get(editContext($post));

        expect($draft)->not->toBeNull()
            ->and($draft->data['title'])->toBe('Drafted title')
            ->and($draft->data)->not->toHaveKeys(['id', 'uuid', 'is_current', 'is_published', 'is_auto']);
    });

    it('ignores payload keys that are not table columns', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title', 'not_a_column' => 'x']);

        expect($store->get(editContext($post))->data['title'])->toBe('Drafted title');
    });

    it('skips saves whose payload has no draftable columns', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['not_a_column' => 'x']);

        expect($store->resolveAutoDraft($post))->toBeNull();
    });

    it('skips payloads that violate database constraints', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => null]);

        expect($store->resolveAutoDraft($post))->toBeNull();
    });

    it('forgets the auto draft without touching the published record', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title']);
        $store->forget(editContext($post));

        $post->refresh();

        expect($store->get(editContext($post)))->toBeNull()
            ->and(Post::query()->whereKey($post->getKey())->exists())->toBeTrue()
            ->and($post->is_current)->toBeTrue();
    });

    it('returns null when the record has no auto draft', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);

        expect((new LaravelDraftsStore)->get(editContext($post)))->toBeNull();
    });

    it('ignores and prunes expired auto drafts', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore(expiryDays: 7);

        $store->put(editContext($post), ['title' => 'Old draft']);

        Post::query()->onlyAutoDrafts()->update(['updated_at' => now()->subDays(8)]);

        expect($store->get(editContext($post)))->toBeNull()
            ->and($store->resolveAutoDraft($post))->toBeNull();
    });
});

describe('create pages (delegated store)', function (): void {
    it('delegates create page drafts to the create page store', function (): void {
        $store = new LaravelDraftsStore(createPageStore: fn (): DatabaseStore => new DatabaseStore);

        $store->put(createContext(), ['title' => 'Drafted title']);

        expect(Post::query()->withDrafts()->count())->toBe(0)
            ->and($store->get(createContext())->data['title'])->toBe('Drafted title');

        $store->forget(createContext());

        expect($store->get(createContext()))->toBeNull();
    });

    it('defaults to a database create page store when none is provided', function (): void {
        expect((new LaravelDraftsStore)->getCreatePageStore())->toBeInstanceOf(DatabaseStore::class);
    });

    it('refuses to delegate create page drafts to itself', function (): void {
        $store = new LaravelDraftsStore(createPageStore: fn (): LaravelDraftsStore => new LaravelDraftsStore);

        $store->getCreatePageStore();
    })->throws(RuntimeException::class, 'cannot delegate create page drafts to itself');
});

describe('manager create page store resolution', function (): void {
    it('uses the configured create_store', function (): void {
        config()->set('filament-draft-recovery.laravel-drafts.create_store', 'database');

        $store = DraftRecovery::driver('laravel-drafts');

        expect($store->getCreatePageStore())->toBeInstanceOf(DatabaseStore::class);
    });

    it('falls back to the default store', function (): void {
        config()->set('filament-draft-recovery.store', 'local-storage');

        $store = DraftRecovery::driver('laravel-drafts');

        expect($store->getCreatePageStore())->toBeInstanceOf(LocalStorageStore::class);
    });

    it('uses the database store when the default store is laravel-drafts', function (): void {
        config()->set('filament-draft-recovery.store', 'laravel-drafts');

        $store = DraftRecovery::driver('laravel-drafts');

        expect($store->getCreatePageStore())->toBeInstanceOf(DatabaseStore::class);
    });
});
