<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Query-string parameter names
    |--------------------------------------------------------------------------
    |
    | The request parameters this package reads. The shipped defaults are the
    | frozen wire contract the frontend already sends — do not change them
    | unless the frontend changes too.
    |
    |   filter[<name>]=<value>       sort=-created_at,name
    |
    */

    'parameters' => [
        'filter' => 'filter',
        'sort' => 'sort',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Names and bounds used by the HasPageSize FormRequest trait. `per_page`
    | is validated against `max_per_page` (yielding a 422 above it) and also
    | hard-capped inside perPage() as defence in depth. `default_per_page` is
    | used when `per_page` is absent or invalid; left unset, it is 20, or
    | `max_per_page` when that is lower. Set above `max_per_page`, it throws.
    |
    */

    'pagination' => [
        'page_name' => 'page',
        'per_page_name' => 'per_page',
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Unknown-parameter behaviour
    |--------------------------------------------------------------------------
    |
    | How to treat a filter or sort key that was not allow-listed. Values map
    | to the UnknownParameterMode enum:
    |
    |   'reject'  => throw an HTTP 400 (default)
    |   'ignore'  => silently drop the key and apply only the allow-listed ones
    |                (a sort string dropped entirely falls back to defaultSort())
    |
    */

    'mode' => [
        'unknown_filter' => 'reject',
        'unknown_sort' => 'reject',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request limits (DoS hardening)
    |--------------------------------------------------------------------------
    |
    | Caps on request-driven work so a cheap query string can't be turned into
    | an expensive query. Values above these limits are truncated (never grown):
    |
    |   max_filter_values => most comma/array items honoured per filter value
    |   max_value_length  => most characters kept per individual filter value
    |   max_sorts         => most sort columns applied (after de-duplication;
    |                        only allow-listed sorts count)
    |
    */

    'limits' => [
        'max_filter_values' => 50,
        'max_value_length' => 255,
        'max_sorts' => 5,
    ],

];
