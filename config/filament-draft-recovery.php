<?php

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

    'laravel-drafts' => [
        // Must be a model that uses Oddvalue\LaravelDrafts\Concerns\HasDrafts
        // and exposes "key" and "payload" attributes. The default extends the
        // database store's model; run the add_drafts_columns migration.
        'model' => null,
    ],

];
