<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A non-soft-deletable model, to exercise the TrashedFilter guard path.
 *
 * @property int $id
 * @property string $name
 */
final class Author extends Model
{
    protected $guarded = [];
}
