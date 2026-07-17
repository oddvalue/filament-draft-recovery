<?php

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\DraftRecoveryPlugin;
use Oddvalue\FilamentDraftRecovery\Facades\DraftRecovery;
use Oddvalue\FilamentDraftRecovery\Models\RecoverableDraft;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models\Post;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages\CreatePost;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages\CreatePostWithPageStore;
use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Resources\Pages\EditPost;

use function Pest\Livewire\livewire;

function pageContext(string $key, ?Model $record = null): DraftContext
{
    return new DraftContext(
        key: $key,
        modelClass: Post::class,
        operation: $record instanceof Model ? 'edit' : 'create',
        record: $record,
        userId: auth()->id(),
    );
}

it('injects the draft recovery component in client mode with a create page key', function (): void {
    $user = actingAsTestUser();

    livewire(CreatePost::class)
        ->assertOk()
        ->assertSeeHtml('draftRecovery(')
        ->assertSeeHtml(sprintf('filament-draft:%s:testing:posts:create', $user->id))
        ->assertSeeHtml('\u0022mode\u0022:\u0022client\u0022');
});

it('scopes the edit page key to the record', function (): void {
    $user = actingAsTestUser();

    $post = Post::query()->create(['title' => 'Hello']);

    livewire(EditPost::class, ['record' => $post->getKey()])
        ->assertOk()
        ->assertSeeHtml(sprintf('filament-draft:%s:testing:posts:edit:%s', $user->id, $post->getKey()));
});

it('does not persist server drafts when the store is client side', function (): void {
    actingAsTestUser();

    livewire(CreatePost::class)
        ->call('storeRecoverableDraft', ['title' => 'Ignored']);

    expect(RecoverableDraft::query()->count())->toBe(0);
});

it('ignores restore and discard events when the store is client side', function (): void {
    $user = actingAsTestUser();

    $key = sprintf('filament-draft:%s:testing:posts:create', $user->id);

    livewire(CreatePost::class)
        ->dispatch('draft-recovery-restore', key: $key)
        ->dispatch('draft-recovery-discard', key: $key)
        ->assertSchemaStateSet(['title' => null]);
});

it('uses the page level draft store override', function (): void {
    actingAsTestUser();

    livewire(CreatePostWithPageStore::class)
        ->assertSeeHtml('\u0022mode\u0022:\u0022server\u0022');
});

it('uses the panel plugin draft store when registered', function (): void {
    actingAsTestUser();

    Filament::getPanel('testing')->plugin(
        DraftRecoveryPlugin::make()->store('database')
    );

    livewire(CreatePost::class)
        ->assertSeeHtml('\u0022mode\u0022:\u0022server\u0022');
});

it('falls back to the config store when the panel plugin sets none', function (): void {
    actingAsTestUser();

    Filament::getPanel('testing')->plugin(
        DraftRecoveryPlugin::make()
    );

    livewire(CreatePost::class)
        ->assertSeeHtml('\u0022mode\u0022:\u0022client\u0022');
});

it('dispatches a clear event after creating in client mode', function (): void {
    actingAsTestUser();

    livewire(CreatePost::class)
        ->fillForm(['title' => 'New post'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertDispatched('draft-recovery-clear');

    expect(Post::query()->where('title', 'New post')->exists())->toBeTrue();
});

describe('server mode (database store)', function (): void {
    beforeEach(function (): void {
        config()->set('filament-draft-recovery.store', 'database');
    });

    it('renders in server mode', function (): void {
        actingAsTestUser();

        livewire(CreatePost::class)
            ->assertSeeHtml('\u0022mode\u0022:\u0022server\u0022');
    });

    it('persists drafts sent from the client', function (): void {
        $user = actingAsTestUser();

        livewire(CreatePost::class)
            ->call('storeRecoverableDraft', ['title' => 'Drafted title']);

        $draft = DraftRecovery::driver()->get(pageContext(sprintf('filament-draft:%s:testing:posts:create', $user->id)));

        expect($draft)->not->toBeNull()
            ->and($draft->data['title'])->toBe('Drafted title');
    });

    it('offers recovery when a differing draft exists', function (): void {
        $user = actingAsTestUser();

        DraftRecovery::driver()->put(
            pageContext(sprintf('filament-draft:%s:testing:posts:create', $user->id)),
            ['title' => 'Drafted title'],
        );

        livewire(CreatePost::class)
            ->assertNotified();
    });

    it('does not offer recovery when no draft exists', function (): void {
        actingAsTestUser();

        livewire(CreatePost::class)
            ->assertNotNotified();
    });

    it('restores a draft into the form', function (): void {
        $user = actingAsTestUser();

        $key = sprintf('filament-draft:%s:testing:posts:create', $user->id);
        DraftRecovery::driver()->put(pageContext($key), ['title' => 'Drafted title']);

        livewire(CreatePost::class)
            ->dispatch('draft-recovery-restore', key: $key)
            ->assertSchemaStateSet(['title' => 'Drafted title']);
    });

    it('ignores restore events for other keys', function (): void {
        $user = actingAsTestUser();

        DraftRecovery::driver()->put(
            pageContext(sprintf('filament-draft:%s:testing:posts:create', $user->id)),
            ['title' => 'Drafted title'],
        );

        livewire(CreatePost::class)
            ->dispatch('draft-recovery-restore', key: 'some-other-key')
            ->assertSchemaStateSet(['title' => null]);
    });

    it('ignores restore events when no draft exists', function (): void {
        $user = actingAsTestUser();

        livewire(CreatePost::class)
            ->dispatch('draft-recovery-restore', key: sprintf('filament-draft:%s:testing:posts:create', $user->id))
            ->assertSchemaStateSet(['title' => null]);
    });

    it('ignores discard events for other keys', function (): void {
        $user = actingAsTestUser();

        $key = sprintf('filament-draft:%s:testing:posts:create', $user->id);
        DraftRecovery::driver()->put(pageContext($key), ['title' => 'Drafted title']);

        livewire(CreatePost::class)
            ->dispatch('draft-recovery-discard', key: 'some-other-key');

        expect(DraftRecovery::driver()->get(pageContext($key)))->not->toBeNull();
    });

    it('discards a draft', function (): void {
        $user = actingAsTestUser();

        $key = sprintf('filament-draft:%s:testing:posts:create', $user->id);
        DraftRecovery::driver()->put(pageContext($key), ['title' => 'Drafted title']);

        livewire(CreatePost::class)
            ->dispatch('draft-recovery-discard', key: $key);

        expect(DraftRecovery::driver()->get(pageContext($key)))->toBeNull();
    });

    it('clears the stored draft after a successful create', function (): void {
        $user = actingAsTestUser();

        $key = sprintf('filament-draft:%s:testing:posts:create', $user->id);
        DraftRecovery::driver()->put(pageContext($key), ['title' => 'Drafted title']);

        livewire(CreatePost::class)
            ->fillForm(['title' => 'Final title'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertDispatched('draft-recovery-clear');

        expect(DraftRecovery::driver()->get(pageContext($key)))->toBeNull();
    });

    it('clears the stored draft after a successful save', function (): void {
        $user = actingAsTestUser();

        $post = Post::query()->create(['title' => 'Hello']);

        $key = sprintf('filament-draft:%s:testing:posts:edit:%s', $user->id, $post->getKey());
        DraftRecovery::driver()->put(pageContext($key, $post), ['title' => 'Drafted title']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['title' => 'Updated'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertDispatched('draft-recovery-clear');

        expect(DraftRecovery::driver()->get(pageContext($key, $post)))->toBeNull();
    });

    it('drops a draft matching the current form state instead of prompting', function (): void {
        $user = actingAsTestUser();

        $post = Post::query()->create(['title' => 'Hello']);

        $key = sprintf('filament-draft:%s:testing:posts:edit:%s', $user->id, $post->getKey());
        DraftRecovery::driver()->put(pageContext($key, $post), ['title' => 'Hello', 'body' => null]);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->assertNotNotified();

        expect(DraftRecovery::driver()->get(pageContext($key, $post)))->toBeNull();
    });
});

describe('server mode (laravel-drafts store)', function (): void {
    beforeEach(function (): void {
        config()->set('filament-draft-recovery.store', 'laravel-drafts');
    });

    it('stores drafts as a non-current auto draft of the edited record', function (): void {
        actingAsTestUser();

        $post = Post::query()->create(['title' => 'Published title']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->call('storeRecoverableDraft', ['title' => 'Drafted title']);

        $post->refresh();
        $autoDraft = DraftRecovery::driver()->resolveAutoDraft($post);

        expect($post->title)->toBe('Published title')
            ->and($post->is_current)->toBeTrue()
            ->and($autoDraft?->title)->toBe('Drafted title')
            ->and($autoDraft->is_current)->toBeFalse();
    });

    it('offers recovery and restores the auto draft into the form', function (): void {
        actingAsTestUser();

        $post = Post::query()->create(['title' => 'Published title']);
        $key = 'filament-draft:' . auth()->id() . (':testing:posts:edit:' . $post->getKey());
        DraftRecovery::driver()->put(pageContext($key, $post), ['title' => 'Drafted title']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->assertNotified()
            ->dispatch('draft-recovery-restore', key: $key)
            ->assertSchemaStateSet(['title' => 'Drafted title']);
    });

    it('clears the auto draft after a successful save', function (): void {
        actingAsTestUser();

        $post = Post::query()->create(['title' => 'Published title']);
        $key = 'filament-draft:' . auth()->id() . (':testing:posts:edit:' . $post->getKey());
        DraftRecovery::driver()->put(pageContext($key, $post), ['title' => 'Drafted title']);

        livewire(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['title' => 'Final title'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertDispatched('draft-recovery-clear');

        $post->refresh();

        expect(DraftRecovery::driver()->resolveAutoDraft($post))->toBeNull()
            ->and($post->title)->toBe('Final title')
            ->and($post->is_current)->toBeTrue();
    });

    it('renders create pages in the mode of the delegated create page store', function (): void {
        actingAsTestUser();

        // Default create page store falls back to the config default, which
        // here is laravel-drafts itself — so the database store steps in.
        livewire(CreatePost::class)
            ->assertSeeHtml('\u0022mode\u0022:\u0022server\u0022');

        config()->set('filament-draft-recovery.laravel-drafts.create_store', 'local-storage');

        livewire(CreatePost::class)
            ->assertSeeHtml('\u0022mode\u0022:\u0022client\u0022');
    });

    it('delegates create page drafts to the configured create page store', function (): void {
        $user = actingAsTestUser();

        config()->set('filament-draft-recovery.laravel-drafts.create_store', 'database');

        $key = sprintf('filament-draft:%s:testing:posts:create', $user->id);

        livewire(CreatePost::class)
            ->call('storeRecoverableDraft', ['title' => 'Drafted title']);

        expect(RecoverableDraft::query()->where('key', $key)->exists())->toBeTrue()
            ->and(Post::query()->withDrafts()->count())->toBe(0);

        livewire(CreatePost::class)
            ->fillForm(['title' => 'Final title'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertDispatched('draft-recovery-clear');

        expect(RecoverableDraft::query()->where('key', $key)->exists())->toBeFalse();
    });
});
