<?php

namespace Oddvalue\FilamentDraftRecovery;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentDraftRecoveryServiceProvider extends PackageServiceProvider
{
    public const PURGE_COOKIE = 'filament_draft_recovery_purge';

    public static string $name = 'filament-draft-recovery';

    public static string $viewNamespace = 'filament-draft-recovery';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigrations($this->getMigrations())
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('oddvalue/filament-draft-recovery');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DraftStoreManager::class, fn (): DraftStoreManager => new DraftStoreManager($this->app));
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        // localStorage can only be cleared by a script on a rendered page, so
        // an explicit logout queues a short-lived cookie and the next panel
        // page render (normally the login redirect) purges the drafts and
        // consumes the cookie. Session expiry fires no Logout event — those
        // drafts survive for recovery.
        Event::listen(Logout::class, function (): void {
            if (config('filament-draft-recovery.purge_on_logout') === true) {
                Cookie::queue(self::PURGE_COOKIE, '1', 5);
            }
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_START,
            function (): string {
                if (request()->cookie(self::PURGE_COOKIE) === null) {
                    return '';
                }

                Cookie::queue(Cookie::forget(self::PURGE_COOKIE));

                return view('filament-draft-recovery::purge-local-drafts', [
                    'keyPrefix' => config('filament-draft-recovery.key_prefix'),
                ])->render();
            },
        );
    }

    protected function getAssetPackageName(): string
    {
        return 'oddvalue/filament-draft-recovery';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            AlpineComponent::make('draft-recovery', __DIR__ . '/../resources/dist/components/draft-recovery.js'),
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_recoverable_drafts_table',
        ];
    }
}
