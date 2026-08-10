<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A non-soft-deletable model, to exercise the TrashedFilter guard path.
 *
 * @property int $id
 * @property string $name
 */
final class Author extends Model
{
    protected $guarded = [];

    /**
     * The to-many relation `RelationOperatorFilter` is proved against: an author with
     * several posts is exactly the row a negated `whereHas` would wrongly keep.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
