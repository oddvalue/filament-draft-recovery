<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $key
 * @property array<string, mixed>|null $payload
 * @property ?Carbon $updated_at
 */
class RecoverableDraft extends Model
{
    protected $table = 'recoverable_drafts';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
