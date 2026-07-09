# Changelog

All notable changes to `query-builder-for-laravel` will be documented in this file.

## 1.0.0 - Unreleased

- Initial release: allow-list-driven filters, sorts and pagination for Laravel API list
  endpoints.
- `QueryBuilder::for()` fluent wrapper that transparently forwards to the underlying Eloquent
  builder.
- Filters: `exact`, `partial`, `scope`, `callback`, `trashed`, and `custom`.
- Sorts: `field`, `custom`, and `defaultSort` (single or multi-column).
- `HasPageSize` FormRequest trait for validated, capped `per_page` handling.
- Configurable unknown-parameter behaviour (`reject` → HTTP 400, or `ignore`) via the
  `UnknownParameterMode` enum.
