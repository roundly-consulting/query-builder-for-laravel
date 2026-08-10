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
 * @property list<string> $tags
 */
final class Post extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'views' => 'integer',
        'tags' => 'array',
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

    /**
     * A single-argument scope whose one parameter is a list of titles. It lets a
     * test assert that a comma/array filter value arrives as one array argument,
     * never spread into several positional arguments.
     *
     * @param  Builder<Post>  $query
     * @param  string|list<string>  $titles
     */
    public function scopeTitles(Builder $query, string|array $titles): void
    {
        $query->whereIn('title', (array) $titles);
    }
}
