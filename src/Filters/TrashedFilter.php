<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use RoundlyConsulting\QueryBuilder\Contracts\Filter;

/**
 * Toggles the soft-delete scope for a model using SoftDeletes:
 *   `with` → include trashed, `only` → only trashed, anything else → default.
 *
 * Implemented by lifting the SoftDeletingScope global scope (the same thing
 * withTrashed()/onlyTrashed() do) so it type-checks on a generic model builder.
 */
final class TrashedFilter implements Filter
{
    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, mixed $value, string $property): void
    {
        if ($value === 'with') {
            $query->withoutGlobalScope(SoftDeletingScope::class);

            return;
        }

        if ($value === 'only') {
            $model = $query->getModel();

            // Honour a model's custom soft-delete column (const DELETED_AT /
            // getDeletedAtColumn()). The qualifier lives on the SoftDeletes
            // trait, not base Model, so guard it for Larastan.
            $column = method_exists($model, 'getQualifiedDeletedAtColumn')
                ? (string) $model->getQualifiedDeletedAtColumn()
                : $model->getTable().'.deleted_at';

            $query
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->whereNotNull($column);

            return;
        }

        // Default: keep the SoftDeletingScope in place, so only non-trashed rows return.
    }
}
