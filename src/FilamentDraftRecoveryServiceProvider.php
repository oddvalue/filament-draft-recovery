<?php

namespace Oddvalue\FilamentDraftRecovery;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentDraftRecoveryServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-draft-recovery';

    public static string $viewNamespace = 'filament-draft-recovery';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigrations($this->getMigrations())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('oddvalue/filament-draft-recovery');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DraftStoreManager::class, fn ($app) => new DraftStoreManager($app));
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );
    }

    protected function getAssetPackageName(): ?string
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
            'add_drafts_columns_to_recoverable_drafts_table',
        ];
    }
}
