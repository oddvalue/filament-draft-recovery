<?php

declare(strict_types=1);

namespace Oddvalue\FilamentDraftRecovery\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * Everything a draft store needs to locate a draft: the unique key (always
 * sufficient for key/value stores), plus the page's model class, operation,
 * record (edit pages only), and user for record-based stores.
 */
class DraftContext
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  'create'|'edit'  $operation
     */
    public function __construct(
        public readonly string $key,
        public readonly string $modelClass,
        public readonly string $operation,
        public readonly ?Model $record = null,
        public readonly int | string | null $userId = null,
    ) {}
}
