<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property string $status
 * @property int $views
 * @property bool $active
 * @property int $author_id
 */
final class Post extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'views' => 'integer',
    ];

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeViewsBetween(Builder $query, int $min, int $max): void
    {
        $query->whereBetween('views', [$min, $max]);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query, bool $published): void
    {
        $query->where('status', $published ? 'published' : 'draft');
    }
}
