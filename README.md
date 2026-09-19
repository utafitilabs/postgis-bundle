# PostGIS for Symfony!

[![CI](https://github.com/utafitilabs/postgis-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/utafitilabs/postgis-bundle/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/utafitilabs/postgis-bundle)](https://packagist.org/packages/utafitilabs/postgis-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/utafitilabs/postgis-bundle)](https://packagist.org/packages/utafitilabs/postgis-bundle)
[![License](https://img.shields.io/packagist/l/utafitilabs/postgis-bundle)](LICENSE)

This bundle gives Doctrine ORM 3 / DBAL 4 first-class **PostGIS** support: spatial column
types exchanged as **GeoJSON**, `ST_*` DQL functions, **automatic GiST indexing**, and
**typed geometry columns with churn-free migrations** — no hand-written spatial SQL,
no configuration. Enable the bundle and go.

> Part of the [utafiti tools](https://github.com/utafitilabs).

## Why

Doctrine ships no spatial types, and PostGIS stores every shape as the base `geometry`
type — so the usual approaches either hand-write DDL or fight `migrations:diff` churn.
This bundle handles all of it: declare a column, get a GiST-indexed, GeoJSON-friendly,
optionally shape-constrained column with clean diffs.

## Install

Applications using [Symfony Flex](https://symfony.com/doc/current/setup.html):

```console
composer require utafitilabs/postgis-bundle
```

Applications without Symfony Flex — after requiring the package, enable the bundle:

```php
// config/bundles.php
return [
    // ...
    UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle::class => ['all' => true],
];
```

That one line registers the spatial types, the DB-type mappings, the `ST_*` DQL functions,
the `USING gist` platform middleware, the typmod-aware schema manager, and the auto-GiST
schema listener. Nothing else to configure.

The bundle's configuration root key is `utafiti_labs_post_gis`. It takes no options — it
is simply the key reserved in `config/packages/`, and the prefix of the bundle's internal
service ids.

## A taste

```php
use Doctrine\ORM\Mapping as ORM;

class Area
{
    // Generic geometry — accepts any shape, SRID 4326, diff-clean.
    #[ORM\Column(type: 'geometry')]
    public ?string $footprint = null;   // GeoJSON string in, GeoJSON string out

    // Typed — PostGIS enforces the shape via the typmod, still diff-clean.
    #[ORM\Column(type: 'multipolygon')]
    public ?string $boundary = null;    // geometry(MultiPolygon,4326)
}
```

```php
$em->createQuery(
    'SELECT COUNT(a.id) FROM App\Entity\Area a
     WHERE ST_Intersects(a.boundary, ST_GeomFromGeoJSON(:poly)) = true'
)->setParameter('poly', $geoJsonPolygon)->getSingleScalarResult();
```

Column types: `geometry`, `geography`, `point`, `linestring`, `polygon`, `multipolygon`.
DQL functions: `ST_AsGeoJSON`, `ST_GeomFromGeoJSON`, `ST_Intersects`, `ST_DWithin`, `ST_Union`,
`ST_SimplifyPreserveTopology`, `ST_MakeValid`, `ST_CollectionExtract`, `ST_Multi`,
`ST_Area`, and `Geography(g)` (the `::geography` cast, for geodesic measurement:
`ST_Area(Geography(t.geom))` = m²) — enough to express dissolve-style aggregation
entirely in DQL.

Prefer no DQL at all? Extend the repository base and the St methods just exist:

```php
use UtafitiLabs\PostGISBundle\Repository\SpatialEntityRepository;

final class AreaRepository extends SpatialEntityRepository {}   // that's all

$areas->findStIntersecting($geoJson);        // GiST-backed ST_Intersects
$areas->stAreaKm2(['source' => 'wdpa']);     // geodesic km², findBy-style criteria
```

Every method carries the `St` marker — the bundle's signature, never confusable
with a Doctrine core method. The geometry column is discovered from the entity's
own metadata; an entity without one is rejected at construction with a teaching
message.
Every geometry/geography column gets a `USING gist` index in generated migrations, automatically.

## Documentation

Read the documentation at [docs/index.md](docs/index.md) — including
[generic vs. typed geometry columns](docs/geometry-columns.md) and how typed columns
stay `migrations:diff`-clean.

## Requirements

- PHP **8.4+** · Symfony **7.3+ / 8**
- `doctrine/dbal` **^4**, `doctrine/orm` **^3.5**, `doctrine/doctrine-bundle` **^3**
- PostgreSQL with the **PostGIS** extension

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). CI enforces the standard (php-cs-fixer, PHPStan max,
PHPUnit against real PostGIS, lowest→newest dependency matrix) on every pull request.

## Credits

- [Ezekiel Mjema](https://github.com/eemjema)
- [All Contributors](https://github.com/utafitilabs/postgis-bundle/graphs/contributors)

## License

MIT License (MIT): see the [LICENSE](LICENSE) file for more details.