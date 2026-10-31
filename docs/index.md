# UtafitiLabsPostGISBundle

First-class **PostGIS** support for **Doctrine ORM 3 / DBAL 4** on **Symfony**: spatial
column types exchanged as GeoJSON, `ST_*` DQL functions, automatic GiST indexing, and
typed geometry columns with churn-free migrations.

## Contents

- [Installation](#installation)
- [Requirements](#requirements)
- [Column types](#column-types)
- [DQL functions](#dql-functions)
  - [Distances in metres](#distances-in-metres)
  - [Nearest neighbours](#nearest-neighbours)
- [Automatic GiST indexes](#automatic-gist-indexes)
- [Further reading](#further-reading)

## Installation

Applications that use [Symfony Flex](https://symfony.com/doc/current/setup.html#creating-symfony-applications):

```console
composer require utafitilabs/postgis-bundle
```

Applications that don't use Symfony Flex — after requiring the package, enable the bundle:

```php
// config/bundles.php
return [
    // ...
    UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle::class => ['all' => true],
];
```

There is nothing to configure: enabling the bundle registers the spatial Doctrine types,
the database-type mappings, the `ST_*` DQL functions, the `USING gist` platform
middleware, the typmod-aware schema manager, and the auto-GiST schema listener.

The bundle's configuration root key is `utafiti_labs_post_gis` (the DI extension alias).
It accepts no options; it is the key reserved in `config/packages/` and the prefix of the
bundle's internal service ids.

## Requirements

- PHP **8.4+**
- `doctrine/dbal` **^4**, `doctrine/orm` **^3.5**, `doctrine/doctrine-bundle` **^3**
- Symfony **7.3+ / 8**
- PostgreSQL with the **PostGIS** extension

## Column types

| Doctrine type | Database column |
|---|---|
| `geometry` | `geometry` (any shape, SRID 4326) |
| `geography` | `geography` |
| `point` | `geometry(Point,4326)` |
| `linestring` | `geometry(LineString,4326)` |
| `polygon` | `geometry(Polygon,4326)` |
| `multipolygon` | `geometry(MultiPolygon,4326)` |

```php
use Doctrine\ORM\Mapping as ORM;

class Area
{
    #[ORM\Column(type: 'geometry')]
    public ?string $footprint = null;   // GeoJSON string in, GeoJSON string out

    #[ORM\Column(type: 'multipolygon')]
    public ?string $boundary = null;    // geometry(MultiPolygon,4326)
}
```

PHP-side values are plain **GeoJSON strings** in both directions; conversion happens
inside PostgreSQL via `ST_AsGeoJSON` / `ST_GeomFromGeoJSON`.

See [Generic vs. typed geometry columns](geometry-columns.md) for how typed columns stay
diff-clean under `doctrine:migrations:diff`.

## DQL functions

Registered `ST_*` functions: `ST_AsGeoJSON`, `ST_GeomFromGeoJSON`, `ST_Intersects`, `ST_DWithin`
(`ST_DWithin(Geography(a), Geography(b), :meters) = true` for geodesic radius filters).

```php
$em->createQuery(
    'SELECT COUNT(a.id) FROM App\Entity\Area a
     WHERE ST_Intersects(a.boundary, ST_GeomFromGeoJSON(:poly)) = true'
)->setParameter('poly', $geoJsonPolygon)->getSingleScalarResult();
```

| DQL function | Returns | Notes |
|---|---|---|
| `ST_AsGeoJSON(g[, digits[, options]])` | text | GeoJSON of a shape |
| `ST_GeomFromGeoJSON(text)` | geometry | SRID 4326 |
| `ST_Intersects(a, b)` | boolean | compare with `= true`; GiST-backed |
| `ST_DWithin(a, b, distance[, use_spheroid])` | boolean | metres when both sides are geography |
| `ST_Distance(a, b[, use_spheroid])` | float | metres when either side is geography, CRS units on geometry |
| `ST_Length(g[, use_spheroid])` | float | metres on geography, CRS units on geometry |
| `ST_Area(g)` | float | m² on geography, CRS units² on geometry |
| `ST_Buffer(g, radius[, style])` | geometry / geography | radius in metres on geography |
| `ST_Intersection(a, b[, gridSize])` | geometry | the part both shapes share |
| `ST_Union(g)` / `ST_Union(a, b)` | geometry | aggregate (dissolve) or pairwise |
| `ST_SimplifyPreserveTopology(g, tolerance)` | geometry | never produces invalid shapes |
| `ST_MakeValid(g)` | geometry | repairs without losing vertices |
| `ST_CollectionExtract(g, type)` | geometry | 1 points, 2 lines, 3 polygons |
| `ST_Multi(g)` | geometry | Polygon → MultiPolygon |
| `ST_KnnDistance(a, b)` | float | the `<->` operator; an ordering key, not metres |
| `Geography(g)` | geography | the `::geography` cast |

### Distances in metres

`ST_Distance` on geography returns metres, so it can be selected and ordered by.
PostgreSQL casts the geometry side to geography itself, so one `Geography()` is enough:

```php
// [0 => Venue, 'metres' => float] per row, nearest first
$em->createQuery(
    'SELECT v, ST_Distance(Geography(v.location), ST_GeomFromGeoJSON(:p)) AS metres
     FROM App\Entity\Venue v
     ORDER BY metres ASC'
)->setParameter('p', $geoJsonPoint)->setMaxResults(20)->getResult();
```

Wrapping a `geometry` column in `Geography()` hides it from the column's GiST index, so
this form measures every row. To filter by a radius on a large table, prefilter on the
column itself and keep the geography cast for the exact distance.

### Nearest neighbours

`ST_KnnDistance(a, b)` is PostGIS's `<->` operator. In `ORDER BY` with a limit, PostgreSQL
walks the GiST index in distance order instead of sorting every row:

```php
$em->createQuery(
    'SELECT v FROM App\Entity\Venue v
     ORDER BY ST_KnnDistance(v.location, ST_GeomFromGeoJSON(:p)) ASC'
)->setParameter('p', $geoJsonPoint)->setMaxResults(10)->getResult();
```

On a geometry column the value is planar (degrees for SRID 4326), so the order is
planar too: use it to rank, and `ST_Distance` on geography when the number has to be metres.

## Automatic GiST indexes

Every geometry/geography column gets a `USING gist` index in the generated schema and in
migrations — no hand-written DDL.

## Further reading

- [Generic vs. typed geometry columns](geometry-columns.md)