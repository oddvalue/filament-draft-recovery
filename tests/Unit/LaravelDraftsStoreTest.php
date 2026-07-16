<?php

use Illuminate\Database\Eloquent\Model;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Stores\LaravelDraftsStore;
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
    (new LaravelDraftsStore)->get(new DraftContext(
        key: 'a-key',
        modelClass: User::class,
        operation: 'create',
    ));
})->throws(RuntimeException::class, 'HasDrafts');

describe('edit pages', function (): void {
    it('stores the draft as a draft revision of the record', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title']);

        $post->refresh();

        expect($post->title)->toBe('Published title')
            ->and($post->drafts()->count())->toBe(1)
            ->and($post->drafts()->first()->title)->toBe('Drafted title');
    });

    it('retrieves the draft payload from the record draft', function (): void {
        $post = Post::query()->create(['title' => 'Published title', 'body' => 'Body']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title']);

        $draft = $store->get(editContext($post));

        expect($draft)->not->toBeNull()
            ->and($draft->data['title'])->toBe('Drafted title')
            ->and($draft->data)->not->toHaveKeys(['id', 'uuid', 'is_current', 'is_published']);
    });

    it('ignores payload keys that are not table columns', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title', 'not_a_column' => 'x']);

        expect($store->get(editContext($post))->data['title'])->toBe('Drafted title');
    });

    it('forgets draft revisions without touching the published record', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);
        $store = new LaravelDraftsStore;

        $store->put(editContext($post), ['title' => 'Drafted title']);
        $store->forget(editContext($post));

        expect($store->get(editContext($post)))->toBeNull()
            ->and(Post::query()->whereKey($post->getKey())->exists())->toBeTrue();
    });

    it('returns null when the record has no draft', function (): void {
        $post = Post::query()->create(['title' => 'Published title']);

        expect((new LaravelDraftsStore)->get(editContext($post)))->toBeNull();
    });
});

describe('create pages', function (): void {
    beforeEach(function (): void {
        actingAsTestUser();
    });

    it('stores the draft as an unpublished draft record', function (): void {
        $store = new LaravelDraftsStore;

        $store->put(createContext(), ['title' => 'Drafted title']);

        expect(Post::query()->count())->toBe(0)
            ->and(Post::query()->onlyDrafts()->count())->toBe(1)
            ->and(Post::query()->onlyDrafts()->first()->title)->toBe('Drafted title');
    });

    it('updates the same draft record on subsequent saves', function (): void {
        $store = new LaravelDraftsStore;

        $store->put(createContext(), ['title' => 'First']);
        $store->put(createContext(), ['title' => 'Second']);

        expect(Post::query()->onlyDrafts()->count())->toBe(1)
            ->and($store->get(createContext())->data['title'])->toBe('Second');
    });

    it('forgets the draft record', function (): void {
        $store = new LaravelDraftsStore;

        $store->put(createContext(), ['title' => 'Drafted title']);
        $store->forget(createContext());

        expect($store->get(createContext()))->toBeNull()
            ->and(Post::query()->onlyDrafts()->count())->toBe(0);
    });

    it('skips payloads that violate database constraints', function (): void {
        $store = new LaravelDraftsStore;

        $store->put(createContext(), ['title' => null]);

        expect(Post::query()->onlyDrafts()->count())->toBe(0);
    });

    it('scopes create page drafts to the publishing user', function (): void {
        $store = new LaravelDraftsStore;

        $store->put(createContext(), ['title' => 'Mine']);

        $otherUserContext = new DraftContext(
            key: 'create-key',
            modelClass: Post::class,
            operation: 'create',
            userId: 999999,
        );

        expect($store->get($otherUserContext))->toBeNull()
            ->and($store->get(createContext())->data['title'])->toBe('Mine');
    });
});

it('ignores and prunes expired drafts', function (): void {
    $post = Post::query()->create(['title' => 'Published title']);
    $store = new LaravelDraftsStore(expiryDays: 7);

    $store->put(editContext($post), ['title' => 'Old draft']);

    Post::query()->onlyDrafts()->update(['updated_at' => now()->subDays(8)]);

    expect($store->get(editContext($post)))->toBeNull();
});
