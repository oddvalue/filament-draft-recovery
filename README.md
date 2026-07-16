# Filament Draft Recovery

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oddvalue/filament-draft-recovery.svg?style=flat-square)](https://packagist.org/packages/oddvalue/filament-draft-recovery)
![PHP Support](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2Foddvalue%2Ffilament-draft-recovery%2Fmain%2Fcomposer.json&query=require.php&label=PHP)
![Filament Support](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2Foddvalue%2Ffilament-draft-recovery%2Fmain%2Fcomposer.json&query=require%5B'filament%2Ffilament'%5D&label=Filament)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/oddvalue/filament-draft-recovery/tests.yml?label=tests&style=flat-square)](https://github.com/oddvalue/filament-draft-recovery/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/oddvalue/filament-draft-recovery/fix-code-style.yml?label=code%20style&style=flat-square)](https://github.com/oddvalue/filament-draft-recovery/actions?query=workflow%3Afix-code-style+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/oddvalue/filament-draft-recovery.svg?style=flat-square)](https://packagist.org/packages/oddvalue/filament-draft-recovery)
![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/oddvalue/9dd8e508cb2433728d42a258193770eb/raw/filament-draft-recovery-cobertura-coverage.json)

Auto-save draft & crash recovery for Filament v4 create/edit pages, with swappable storage drivers. 100% test coverage, enforced in CI.

While a user edits a create or edit form, the form state is auto-saved (debounced, ~2s). If their browser crashes, the tab closes, or the session expires, returning to the page shows a persistent notification offering to **recover** or **discard** the draft. Drafts are cleared on a successful save and expire after 7 days.

## Storage drivers

| Driver | Where drafts live | Notes |
|---|---|---|
| `local-storage` (default) | The user's browser localStorage | Zero server storage; drafts are plaintext on the user's machine |
| `database` | The `recoverable_drafts` table | Drafts follow the user across devices |
| `laravel-drafts` | **On the model being edited**, via [oddvalue/laravel-drafts](https://github.com/oddvalue/laravel-drafts) | Auto-saves become draft revisions of the record itself |

Custom drivers can be registered with `DraftRecovery::extend()`.

## Installation

```bash
composer require oddvalue/filament-draft-recovery

php artisan filament-draft-recovery:install
```

The install command publishes the config and (for the server-side drivers) the migrations. Skip running the migrations if you only use the `local-storage` driver.

## Usage

Add the trait to a resource's create and/or edit page:

```php
use Filament\Resources\Pages\CreateRecord;
use Oddvalue\FilamentDraftRecovery\Concerns\RecoversDrafts;

class CreatePost extends CreateRecord
{
    use RecoversDrafts;

    protected static string $resource = PostResource::class;
}
```

That's it — the trait injects the JavaScript via the page footer and everything else is automatic.

### Choosing a driver

Set the default in `config/filament-draft-recovery.php` (or `FILAMENT_DRAFT_RECOVERY_STORE`):

```php
'store' => 'database',
```

Per panel:

```php
use Oddvalue\FilamentDraftRecovery\DraftRecoveryPlugin;

$panel->plugin(DraftRecoveryPlugin::make()->store('database'));
```

Per page:

```php
class CreatePost extends CreateRecord
{
    use RecoversDrafts;

    protected ?string $draftStore = 'laravel-drafts';
}
```

### The laravel-drafts driver

```bash
composer require oddvalue/laravel-drafts
```

Drafts are stored **directly on the model being edited**, which must use laravel-drafts' `HasDrafts` trait (and have the drafts columns on its table — see the laravel-drafts docs):

- **Edit pages**: auto-saves upsert a single **auto draft** per record — an unpublished revision that is *not* flagged as current, so the record keeps `is_current` and any intentional draft (`$record->draft`) is untouched. It reads as the record's latest partial draft (`LaravelDraftsStore::resolveAutoDraft($record)`), is updated in place on every auto-save (no revision churn), and is recognised by the flag combination unpublished + not-current + null `published_at`.
- **Create pages**: the first auto-save creates an unpublished record via `createDraft()`; later auto-saves update it in place. Recovery finds the user's latest unpublished draft of the model (scoped by publisher).
- **Clearing** (successful save / discard) deletes only the auto draft row via a query delete — published rows, intentional drafts, and the revision history laravel-drafts keeps are never touched.
- Saves are **best-effort**: payloads that violate column constraints (required fields not yet filled) are skipped and retried on the next auto-save.
- Only real table columns are persisted; form-only keys are dropped. Repeater/relation state is not covered by this driver — use `database` if you need the full form payload.

### Custom drivers

Implement `Oddvalue\FilamentDraftRecovery\Contracts\DraftStore` and register it in a service provider:

```php
use Oddvalue\FilamentDraftRecovery\Contracts\DraftStore;
use Oddvalue\FilamentDraftRecovery\Data\DraftContext;
use Oddvalue\FilamentDraftRecovery\Data\RecoveredDraft;
use Oddvalue\FilamentDraftRecovery\Facades\DraftRecovery;

class RedisDraftStore implements DraftStore
{
    public function isClientSide(): bool
    {
        return false;
    }

    public function get(DraftContext $context): ?RecoveredDraft
    {
        $payload = Redis::get($context->key);

        return $payload ? new RecoveredDraft(data: json_decode($payload, true)) : null;
    }

    public function put(DraftContext $context, array $data): void
    {
        Redis::setex($context->key, 60 * 60 * 24 * 7, json_encode($data));
    }

    public function forget(DraftContext $context): void
    {
        Redis::del($context->key);
    }
}

// In a service provider:
DraftRecovery::extend('redis', fn () => new RedisDraftStore);
```

Then select it like any built-in driver (`'store' => 'redis'`, `DraftRecoveryPlugin::make()->store('redis')`, or `protected ?string $draftStore = 'redis';`).

Every method receives a `DraftContext` carrying the unique `key` (always sufficient for key/value stores) plus the page's `modelClass`, `operation` (`create`/`edit`), `record` (edit pages), and `userId` — everything a record-based store needs.

### Excluding sensitive fields

Drafts may be stored as plaintext (especially with `local-storage`). Exclude anything sensitive:

```php
protected function draftRecoveryExcludedFields(): array
{
    return ['password', 'api_token'];
}
```

Livewire temporary upload markers are always stripped — file uploads cannot be restored from a draft.

## Pages with their own lifecycle hooks

The trait clears drafts from `afterCreate()` / `afterSave()`. A page that defines its own version of either hook **silently overrides the trait's** — call the clear method yourself:

```php
protected function afterSave(): void
{
    $this->dispatchDraftRecoveryClear();

    // your own logic…
}
```

The same applies to `getFooter()`: if your page overrides it, include the view from `Oddvalue\FilamentDraftRecovery\Concerns\RecoversDrafts::getFooter()` in your footer.

> If Filament gains trait-named lifecycle hook support ([filamentphp/filament PR](https://github.com/oddvalue/filament/tree/feature/trait-named-lifecycle-hooks)), this caveat goes away.

## How it works

- The Alpine component (injected via the page footer) snapshots the Livewire form state (`$wire.data`) on input/change, debounced by 2 seconds.
- With `local-storage`, drafts stay in the browser; with a server-side driver the payload is sent to the page via a Livewire call.
- On return, a differing draft triggers a persistent Filament notification with **Recover draft** / **Discard** actions. Recovery merges the draft over the current form state.
- On successful save the page dispatches `draft-recovery-clear`, removing the draft and stopping the auto-save timers.
- Drafts expire after `expiry_days` (default 7); expired localStorage entries are pruned on page load.

## Testing

```bash
composer test
```

## Credits

Inspired by the [auto-save draft & crash recovery Filament example](https://filamentexamples.com/project/auto-save-draft-crash-recovery), re-imagined with client-side storage and swappable drivers. Started from the [Filament plugin skeleton](https://github.com/filamentphp/plugin-skeleton).

## License

MIT — see [LICENSE.md](LICENSE.md).
