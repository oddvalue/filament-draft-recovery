<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Facades;

use Illuminate\Support\Facades\Facade;
use Oddvalue\FilamentDraftRecovery\DraftStoreManager;

/**
 * @method static \Oddvalue\FilamentDraftRecovery\Contracts\DraftStore driver(?string $driver = null)
 * @method static \Oddvalue\FilamentDraftRecovery\DraftStoreManager extend(string $driver, \Closure $callback)
 *
 * @see DraftStoreManager
 */
class DraftRecovery extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DraftStoreManager::class;
    }
}
