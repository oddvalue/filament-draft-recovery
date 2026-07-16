<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Oddvalue\LaravelDrafts\Concerns\HasDrafts;

class Post extends Model
{
    use HasDrafts;

    protected $guarded = [];
}
