<?php

declare(strict_types=1);

namespace RoundlyConsulting\QueryBuilder\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\QueryBuilder\Enums\UnknownParameterMode;

/**
 * Strict reads of the host's query-builder settings. A key that is not set (absent, null or
 * blank — a host's `KEY=`) takes its default; any other invalid value throws
 * {@see InvalidConfigurationException} naming the key.
 * A typo'd mode no longer quietly becomes Reject, and a junk limit is no longer `(int)`
 * cast and clamped to 1.
 *
 * @internal
 */
final class QueryBuilderConfig
{
    public static function unknownFilterMode(): UnknownParameterMode
    {
        return Config::enum('query-builder.mode.unknown_filter', UnknownParameterMode::class, UnknownParameterMode::Reject);
    }

    public static function unknownSortMode(): UnknownParameterMode
    {
        return Config::enum('query-builder.mode.unknown_sort', UnknownParameterMode::class, UnknownParameterMode::Reject);
    }

    public static function maxSorts(): int
    {
        return Config::integer('query-builder.limits.max_sorts', 5, min: 1);
    }

    public static function maxFilterValues(): int
    {
        return Config::integer('query-builder.limits.max_filter_values', 50, min: 1);
    }

    public static function maxValueLength(): int
    {
        return Config::integer('query-builder.limits.max_value_length', 255, min: 1);
    }

    public static function maxPerPage(): int
    {
        return Config::integer('query-builder.pagination.max_per_page', 100, min: 1);
    }

    /** At most the cap: a default above it would hand out pages the cap exists to refuse. */
    public static function defaultPerPage(): int
    {
        return Config::integer('query-builder.pagination.default_per_page', 20, min: 1, max: self::maxPerPage());
    }

    public static function filterParameter(): string
    {
        return self::string('query-builder.parameters.filter', config('query-builder.parameters.filter'), 'filter');
    }

    public static function sortParameter(): string
    {
        return self::string('query-builder.parameters.sort', config('query-builder.parameters.sort'), 'sort');
    }

    public static function pageName(): string
    {
        return self::string('query-builder.pagination.page_name', config('query-builder.pagination.page_name'), 'page');
    }

    public static function perPageName(): string
    {
        return self::string('query-builder.pagination.per_page_name', config('query-builder.pagination.per_page_name'), 'per_page');
    }

    private static function string(string $key, mixed $value, string $default): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }
}
