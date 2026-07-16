<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('draft-recovery', 'oddvalue/filament-draft-recovery') }}"
    x-data="draftRecovery(@js($draftRecoveryConfig))"
    wire:ignore
></div>
