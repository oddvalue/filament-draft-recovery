<?php

declare(strict_types=1);

use Oddvalue\FilamentDraftRecovery\Models\RecoverableDraft;

return [

    /*
    |--------------------------------------------------------------------------
    | Default draft store
    |--------------------------------------------------------------------------
    |
    | Where recoverable form drafts are persisted. Supported: "local-storage"
    | (the user's browser, nothing server-side), "database" (the
    | recoverable_drafts table), "laravel-drafts" (revision-tracked storage
    | via oddvalue/laravel-drafts). Can be overridden per panel via
    | DraftRecoveryPlugin::make()->store(...) or per page by setting the
    | $draftStore property.
    |
    */

    'store' => env('FILAMENT_DRAFT_RECOVERY_STORE', 'local-storage'),

    /*
    |--------------------------------------------------------------------------
    | Save debounce
    |--------------------------------------------------------------------------
    |
    | How long (in milliseconds) after the user stops typing before the form
    | state is saved as a draft. Can be overridden per page by overriding the
    | draftRecoverySaveDebounceMilliseconds() method.
    |
    */

    'save_debounce_milliseconds' => 2000,

    /*
    |--------------------------------------------------------------------------
    | Draft expiry
    |--------------------------------------------------------------------------
    |
    | Drafts older than this many days are ignored and pruned.
    |
    */

    'expiry_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Key prefix
    |--------------------------------------------------------------------------
    |
    | Prepended to every draft key. For the local-storage store this is also
    | used to find (and prune) the plugin's localStorage entries.
    |
    */

    'key_prefix' => 'filament-draft:',

    /*
    |--------------------------------------------------------------------------
    | Excluded fields
    |--------------------------------------------------------------------------
    |
    | Form data keys that are never persisted as a draft, in any store. Merged
    | with each page's draftRecoveryExcludedFields() and with any password
    | inputs found in the form schema (which are always excluded). Dot
    | notation reaches nested state; "*" matches a single segment, e.g.
    | repeater item keys: "members.*.ssn".
    |
    */

    'excluded_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'api_token',
        'secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purge localStorage drafts on logout
    |--------------------------------------------------------------------------
    |
    | When enabled, an explicit logout (Laravel's Logout event) queues a
    | short-lived cookie, and the next panel page render — normally the login
    | redirect — removes every localStorage draft the package has written, so
    | drafts never outlive a logout on a shared machine. Session expiry fires
    | no Logout event, so those drafts remain recoverable.
    |
    */

    'purge_on_logout' => true,

    /*
    |--------------------------------------------------------------------------
    | Server-side stores
    |--------------------------------------------------------------------------
    |
    | Set "encrypt" to true to store database draft payloads encrypted at
    | rest (Laravel's encrypted:array cast, using the app key). The payload
    | column must be a text-type column — ciphertext does not fit a MySQL
    | json column; the shipped migration uses longText.
    |
    */

    'database' => [
        'model' => RecoverableDraft::class,
        'encrypt' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | laravel-drafts store
    |--------------------------------------------------------------------------
    |
    | Edit-page drafts are stored on the page's own model via laravel-drafts'
    | auto draft feature — the model must use the HasDrafts trait and auto
    | drafts must be enabled (drafts.auto_drafts.enabled). Auto drafts only
    | exist for existing records, so create-page drafts are delegated to
    | another store: create_store, falling back to the default store above
    | (or "database" when the default is laravel-drafts itself).
    |
    */

    'laravel-drafts' => [
        'create_store' => null,
    ],

];
