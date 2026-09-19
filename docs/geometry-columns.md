# Geometry columns, SRID, and the DBAL 4 options limitation

## TL;DR

A `geometry` column maps to **`geometry(GEOMETRY, 4326)`** — a *generic* geometry in
WGS84 — and is exchanged with PHP as **GeoJSON text**. It stores any geometry (Point,
LineString, MultiPolygon…) and works fully with GiST indexing, `ST_*` functions, and
GeoJSON. What it does **not** do (yet) is constrain a column to one specific geometry
*type* (e.g. only MultiPolygon). Here's why, and how to opt into stricter typing.

## Why columns are generic

The natural way to carry a geometry's subtype and SRID would be column options:

```php
// This does NOT work on DBAL 4:
#[ORM\Column(type: 'geometry', options: ['geometry_type' => 'MultiPolygon', 'srid' => 4326])]
public ?string $geom = null;
```

**DBAL 4 validates column `options` against a fixed whitelist** — much like Symfony's
`OptionsResolver`. Unknown keys throw:

```
Doctrine\DBAL\Schema\Exception\UnknownColumnOption:
The "geometry_type" column option is not supported.
```

In DBAL 2/3, `options` was a loose bag and spatial libraries smuggled `geometry_type`/`srid`
through it, read back by the custom type's `getSQLDeclaration()`. DBAL 4 closed that door.
So those keys can never reach the type through the ORM, and the type falls back to its
default declaration: `geometry(GEOMETRY, 4326)`.

## What this means in practice

- **Storing/reading/indexing works exactly the same** — a `geometry(Geometry,4326)` column
  holds any geometry in SRID 4326. Your MultiPolygons are fine.
- **SRID 4326 is still enforced** by the typmod.
- **The only thing lost** is PostGIS's column-level `CHECK` that the value is a *specific*
  type. Think of it as dropping one `#[Assert\Type]`-style guard while the column keeps working.

For most applications (e.g. storing MultiPolygons in WGS84) generic geometry is the
right default.

## Opting into strict per-column typing

When you *want* the database to enforce a specific geometry type, two paths:

| Approach | How | Trade-off |
|---|---|---|
| **`columnDefinition`** | `#[ORM\Column(type: 'geometry', columnDefinition: 'geometry(MultiPolygon,4326)')]` | Raw DDL; Doctrine treats the column as opaque, so `migrations:diff` is noisier |
| **Registered sub-types** *(cleanest)* | Register a type per kind (`Type::addType('geometry_multipolygon', …)`) whose `getSQLDeclaration()` emits `geometry(MultiPolygon,4326)`, then `#[ORM\Column(type: 'geometry_multipolygon')]` | A few extra type registrations; **no `options` needed** |

The generic type ships alongside the typed sub-types.

## Note on the unit tests

`GeometryTypeTest` calls `getSQLDeclaration(['geometry_type' => 'MultiPolygon', 'srid' => 4326], …)`
directly and asserts `geometry(MULTIPOLYGON,4326)`. That verifies the method's logic, but
it exercises a path the ORM cannot take on DBAL 4 (the options are rejected upstream). It's
kept as a spec of the declaration logic, not a claim that the option path is reachable.
