# Changelog

All notable changes to `query-builder-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
