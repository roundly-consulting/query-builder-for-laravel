<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Soft-deletable model with a custom soft-delete column, to exercise the
 * TrashedFilter `only` branch honouring getQualifiedDeletedAtColumn().
 *
 * @property int $id
 * @property string $title
 */
final class ArchivedPost extends Model
{
    use SoftDeletes;

    public const string DELETED_AT = 'archived_at';

    protected $table = 'archived_posts';

    protected $guarded = [];
}
