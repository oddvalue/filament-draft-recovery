# Filament Draft Recovery

Auto-save draft & crash recovery for Filament v4 create/edit pages, with swappable storage drivers.

While a user edits a create or edit form, the form state is auto-saved (debounced, ~2s). If their browser crashes, the tab closes, or the session expires, returning to the page shows a persistent notification offering to **recover** or **discard** the draft. Drafts are cleared on a successful save and expire after 7 days.

## Storage drivers

| Driver | Where drafts live | Notes |
|---|---|---|
| `local-storage` (default) | The user's browser localStorage | Zero server storage; drafts are plaintext on the user's machine |
| `database` | The `recoverable_drafts` table | Drafts follow the user across devices |
| `laravel-drafts` | The `recoverable_drafts` table via [oddvalue/laravel-drafts](https://github.com/oddvalue/laravel-drafts) | Every auto-save is kept as a revision |

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
php artisan migrate # runs the add_drafts_columns_to_recoverable_drafts_table migration
```

Payloads are stored on a model using laravel-drafts' `HasDrafts` trait, so each auto-save becomes a revision (laravel-drafts' revision retention applies).

### Custom drivers

```php
use Oddvalue\FilamentDraftRecovery\Facades\DraftRecovery;

DraftRecovery::extend('redis', fn () => new RedisDraftStore);
```

Implement `Oddvalue\FilamentDraftRecovery\Contracts\DraftStore` (`isClientSide()`, `get()`, `put()`, `forget()`).

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
