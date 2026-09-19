# Contributing to UtafitiLabsPostGISBundle

Thanks for your interest! This project holds a high, automated bar so maintainers can
review with confidence. **CI is a gatekeeper: a pull request is only considered for review
once every check below is green.** Please run the gate locally before opening a PR.

## Requirements for every pull request

1. **Test-driven.** No production code without a test. Add/extend:
   - `tests/Unit/` for pure logic (type SQL generation, value conversion, `ST_*` function output) — no database.
   - `tests/Integration/` for database behaviour (GeoJSON round-trip, GiST index creation, churn-free schema diff, `ST_*` execution) — runs against real PostGIS.
2. **`declare(strict_types=1);`** in every PHP file.
3. **Coding standard:** `@Symfony` + `@Symfony:risky` (php-cs-fixer). Run `composer cs:fix`.
4. **Static analysis:** PHPStan at **max**, zero errors.
5. **One class per file.** Each new `ST_*` function is a small subclass of `AbstractSpatialFunction` (declare `functionName()` and, if needed, `minArgs()`/`maxArgs()`), plus a test and a `README.md` entry.
6. **Green gate:** `composer check` passes locally.

## The gate

```bash
composer install
composer cs:fix     # auto-format first
composer check      # cs:check -> phpstan (max) -> tests   (must be green)
```

The same `composer check` runs in CI (GitHub Actions) against a real PostGIS service on
every push and pull request. A PR that does not pass CI will not be reviewed until it does.

## Scope

`UtafitiLabsPostGISBundle` is a **generic** PostGIS integration for Doctrine/Symfony. Please keep it
free of application- or domain-specific code. Bug fixes, new `ST_*` functions with tests,
and documentation improvements are all welcome.
