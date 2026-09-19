# UtafitiLabsPostGISBundle

First-class **PostGIS** support for **Doctrine ORM 3 / DBAL 4** on **Symfony**: spatial
column types exchanged as GeoJSON, `ST_*` DQL functions, automatic GiST indexing, and
typed geometry columns with churn-free migrations.

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

## Automatic GiST indexes

Every geometry/geography column gets a `USING gist` index in the generated schema and in
migrations — no hand-written DDL.

## Further reading

- [Generic vs. typed geometry columns](geometry-columns.md)