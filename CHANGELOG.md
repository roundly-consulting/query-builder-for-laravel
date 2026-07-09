# Changelog

All notable changes to `query-builder-for-laravel` will be documented in this file.

## 1.0.0 - Unreleased

- Initial release: allow-list-driven filters, sorts and pagination for Laravel API list
  endpoints.
- `QueryBuilder::for()` fluent wrapper that transparently forwards to the underlying Eloquent
  builder.
- Filters: `exact`, `partial`, `beginsWith`, `endsWith`, `operator`, `scope`, `callback`,
  `trashed`, and `custom`.
- `AllowedFilter::operator()` with the `FilterOperator` enum (`=`, `!=`, `>`, `>=`, `<`, `<=`)
  for server-side comparison filters, and `beginsWith()` / `endsWith()` anchored partial
  matches — all escaped and applied without changing the wire syntax.
- Sorts: `field`, `custom`, and `defaultSort` (single or multi-column).
- `HasPageSize` FormRequest trait for validated, capped `per_page` handling.
- Configurable unknown-parameter behaviour (`reject` → HTTP 400, or `ignore`) via the
  `UnknownParameterMode` enum; an invalid `mode.*` config value fails closed to `reject`.
- `trashed` `only` mode honours a model's custom soft-delete column.
