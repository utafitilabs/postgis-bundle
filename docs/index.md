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
- [Spatial repository](#spatial-repository)
  - [Radius search](#radius-search)
  - [Your own spatial queries](#your-own-spatial-queries)
- [Automatic GiST indexes](#automatic-gist-indexes)
- [PostGIS tables and migrations](#postgis-tables-and-migrations)
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
middleware, the typmod-aware schema manager, the auto-GiST schema listener, and a
[schema filter for PostGIS's own tables](#postgis-tables-and-migrations).

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
column itself and keep the geography cast for the exact distance — or let
[`findStWithinMetres()`](#radius-search) do both.

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

## Spatial repository

Extend `SpatialEntityRepository` instead of `ServiceEntityRepository` and the `St` methods
are there, with no DQL to write:

```php
// src/Repository/VenueRepository.php
use Doctrine\Persistence\ManagerRegistry;
use UtafitiLabs\PostGISBundle\Repository\SpatialEntityRepository;

/** @extends SpatialEntityRepository<Venue> */
final class VenueRepository extends SpatialEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Venue::class);
    }
}
```

| Method | Returns |
|---|---|
| `findStIntersecting(string $geoJson, array $criteria = [])` | `list<T>` whose shape intersects the GeoJSON geometry |
| `findStWithinMetres(string $geoJson, float $metres, array $criteria = [], ?int $limit = null)` | `list<T>` within `$metres`, nearest first, at most `$limit` |
| `stAreaKm2(array $criteria = [])` | `float`, the geodesic area of the matching rows in km² |

The spatial column is the entity's first `geometry`/`geography` field, read from its
Doctrine metadata; an entity without one is rejected when the repository is built.
`$criteria` narrows any method the way `findBy()` does (`['category' => 'museum']`), and an
unknown field is refused with the list of the entity's fields.

### Radius search

```php
$venues->findStWithinMetres('{"type":"Point","coordinates":[13.4,52.5]}', 1500.0, ['open' => true], 20);
```

The distance is measured on the spheroid (`ST_DWithin` and `ST_Distance` on geography), in
metres, and the result is ordered nearest first. On a `geometry` column the geography cast
cannot use the column's GiST index, so the query first narrows with `ST_DWithin` on the
geometry itself, using a radius in degrees that is always at least as wide as the metre radius at
that latitude, and only then measures on geography. Near the poles (beyond 89°) and when the
radius crosses the antimeridian no degree radius can bound the search, so that prefilter is
left out and every row is measured. A `geography` column needs no prefilter: its own index
serves `ST_DWithin`. Geometry columns are taken to hold longitude/latitude (SRID 4326), as
the bundle's types declare.

A negative radius and a limit below 1 are refused with an `InvalidArgumentException`.

### Your own spatial queries

Subclasses reach the discovered field with `getGeometryField()`, and the query behind the
radius search with `createStWithinMetresQueryBuilder($geoJson, $metres, $criteria)`: root
alias `e`, the GeoJSON bound as `:st_geojson`, the distance selected as the hidden result
variable `st_distance` and ordered by. To return the distance with each entity, add it as a
visible select:

```php
// src/Repository/VenueRepository.php
/** @return list<array{0: Venue, metres: float}> */
public function findNearbyWithDistance(string $geoJson, float $metres): array
{
    return $this->createStWithinMetresQueryBuilder($geoJson, $metres)
        ->addSelect(sprintf('ST_Distance(Geography(e.%s), ST_GeomFromGeoJSON(:st_geojson)) AS metres', $this->getGeometryField()))
        ->getQuery()
        ->getResult();
}
```

## Automatic GiST indexes

Every geometry/geography column gets a `USING gist` index in the generated schema and in
migrations — no hand-written DDL.

## PostGIS tables and migrations

`CREATE EXTENSION postgis` creates `spatial_ref_sys`, and `CREATE EXTENSION
postgis_topology` creates the `topology` schema with its tables and the
`topology.topology_id_seq` sequence. None of them are your entities, so
`doctrine:migrations:diff` would plan to drop them. DBAL already skips `spatial_ref_sys` and
the extension's tables on PostgreSQL, but not the topology sequence:

```sql
DROP SEQUENCE topology.topology_id_seq CASCADE
```

The bundle therefore prepends a `schema_filter` to the DBAL configuration,
`UtafitiLabsPostGISBundle::POSTGIS_SCHEMA_FILTER`:

```yaml
# what the bundle prepends — nothing to write
doctrine:
    dbal:
        schema_filter: '~^(?!(?:[^.]+\.)?spatial_ref_sys$|topology\.)~'
```

It keeps every asset except `spatial_ref_sys` (in any schema) and everything in the
`topology` schema. Your own tables named `layer` or `topology` in your schema are kept.

The filter is prepended, so an application that sets its own `schema_filter` replaces it.
Fold the PostGIS part into your expression when you do:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        # your own rule (skip tables prefixed legacy_) plus PostGIS's assets
        schema_filter: '~^(?!legacy_|(?:[^.]+\.)?spatial_ref_sys$|topology\.)~'
```

Set `schema_filter: ~` to turn filtering off altogether. DoctrineBundle reads
`schema_filter` per connection, and the bundle prepends it at the top level of
`doctrine.dbal`, which is the `default` connection's; with several connections under
`connections`, set it on each other connection that holds PostGIS.

## Further reading

- [Generic vs. typed geometry columns](geometry-columns.md)