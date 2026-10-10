<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The seeded table of the shared `filter-sql-cases.json` contract — created by that test from
 * the fixture's own column list, never by a migration.
 *
 * @property int $id
 */
final class ParityItem extends Model
{
    public $timestamps = false;

    protected $table = 'parity_items';

    protected $guarded = [];
}
