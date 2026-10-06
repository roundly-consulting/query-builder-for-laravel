# Changelog

All notable changes to `query-builder-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Fixed

- `AllowedFilter::operator($name, FilterOperator::NotEqual)` with a comma list now means "none of
  these" (`not in`); it used to OR the `!=` clauses and match every row. It stays plain SQL, so
  rows where the column is NULL are not included (the client-chosen `not:` still includes them).
- Empty filter values are now "no value" in `partial`, `beginsWith`, `endsWith`, `exact`,
  `operator` and `operators`: an empty element (`a,,b`, a trailing comma) is dropped, and a filter
  left with nothing (`filter[title]=`, `filter[status]=,`, `contains:`) adds no constraint. Before,
  a stray comma turned a search into `like '%%'` (every row), an empty `exact` matched no rows,
  and an empty partial value hid rows whose column is NULL.
- An empty value on a typed filter (`AllowedFilter::boolean()`, or `operators()` with a `Uuid`,
  `Id` or `Boolean` shape) now adds no constraint instead of matching no rows, the same as
  `nullable()`. A value that does not fit the column (`filter[active]=maybe`) still matches nothing.

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Allow-list-driven filtering, sorting and pagination for API list endpoints:
  `QueryBuilder::for()` reads `filter[...]`, `sort`, `page` and `per_page` and applies only what
  the controller permits.
- Filters via `AllowedFilter`: `exact`, `boolean`, `partial`, `beginsWith`, `endsWith`, fixed
  `operator`, client-chosen `operators` tokens (`not`, `contains`, `gte`, …), `nullable`,
  `relation`, `scope`, `callback`, `trashed` and `custom`.
- `true` / `false` reach a filter as text unless it opts into booleans (`boolean()`,
  `FilterValueShape::Boolean`, or `booleans: true` on `scope`, `callback` and `custom`), so a text
  search for "false" stays a search.
- Injection-safe `LIKE` matching with escaped wildcards on SQLite, MySQL and PostgreSQL; case
  folding follows each engine (documented per engine in the README).
- `FilterValueShape` typing (`Text`, `Uuid`, `Id`, `Boolean`), so a malformed value on a typed
  column returns no rows instead of a database error.
- Sorts via `allowedSorts()` and `AllowedSort` (`field`, `custom`), with `-` for descending,
  multi-sort and `defaultSort()`.
- The `HasPageSize` form-request concern (`pageSizeRules()`, `perPage()`) with a configurable
  page-size cap and page parameter name.
- Unknown filters and sorts are rejected with a 400 (`UnknownFilter` / `UnknownSort`), or
  silently ignored via config — an ignored sort string falls back to `defaultSort()`.
- `AllowListAlreadyApplied` when an allow-list or default sort is declared after the request was
  already applied by a forwarded builder call, instead of silently ignoring it.
- Request-size limits on filter values and sort columns (only allow-listed sorts count towards
  `limits.max_sorts`), and opt-in argument spreading for scope filters.
- `Filter` and `Sort` contracts for your own filter and sort classes.
