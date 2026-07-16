<?php

namespace Oddvalue\FilamentDraftRecovery\Data;

use Carbon\CarbonInterface;

class RecoveredDraft
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?CarbonInterface $savedAt = null,
    ) {}
}
