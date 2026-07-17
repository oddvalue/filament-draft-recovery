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
    | Server-side stores
    |--------------------------------------------------------------------------
    */

    'database' => [
        'model' => RecoverableDraft::class,
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
