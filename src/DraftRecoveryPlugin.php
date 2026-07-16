<?php

namespace Oddvalue\FilamentDraftRecovery;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Optional per-panel configuration. The RecoversDrafts trait works without
 * registering the plugin (falling back to the package config); register it
 * to set a panel-wide draft store:
 *
 *     $panel->plugin(DraftRecoveryPlugin::make()->store('database'))
 */
class DraftRecoveryPlugin implements Plugin
{
    public const ID = 'filament-draft-recovery';

    protected ?string $store = null;

    public function getId(): string
    {
        return static::ID;
    }

    public function store(?string $store): static
    {
        $this->store = $store;

        return $this;
    }

    public function getStore(): ?string
    {
        return $this->store;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
