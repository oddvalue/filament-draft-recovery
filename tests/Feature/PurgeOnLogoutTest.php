<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Oddvalue\FilamentDraftRecovery\FilamentDraftRecoveryServiceProvider;

it('queues the purge cookie on logout', function (): void {
    actingAsTestUser();

    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->post(route('filament.testing.auth.logout'))
        ->assertRedirect()
        ->assertCookie(FilamentDraftRecoveryServiceProvider::PURGE_COOKIE);
});

it('does not queue the purge cookie when purge_on_logout is disabled', function (): void {
    config()->set('filament-draft-recovery.purge_on_logout', false);

    actingAsTestUser();

    $this->withoutMiddleware(VerifyCsrfToken::class)
        ->post(route('filament.testing.auth.logout'))
        ->assertRedirect()
        ->assertCookieMissing(FilamentDraftRecoveryServiceProvider::PURGE_COOKIE);
});

it('purges localStorage drafts on the page rendered after logout and consumes the cookie', function (): void {
    $this->withCookie(FilamentDraftRecoveryServiceProvider::PURGE_COOKIE, '1')
        ->get(route('filament.testing.auth.login'))
        ->assertOk()
        ->assertSee('filament-draft:')
        ->assertCookieExpired(FilamentDraftRecoveryServiceProvider::PURGE_COOKIE);
});

it('does not purge on an ordinary page render', function (): void {
    $this->get(route('filament.testing.auth.login'))
        ->assertOk()
        ->assertDontSee('filament-draft:');
});
