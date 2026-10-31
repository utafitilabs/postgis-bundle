# Changelog

All notable changes to `utafitilabs/postgis-bundle` are documented here.

## Contents

- [Unreleased](#unreleased)
- [v0.2.0](#v020--2026-10-31)
- [v0.1.0](#v010--2026-09-21)

## Unreleased

## v0.2.0 — 2026-10-31

### Added

- DQL functions `ST_Distance` and `ST_Length` (metres on geography), `ST_Buffer`,
  `ST_Intersection`, and `ST_KnnDistance(a, b)`, the `<->` operator for index-ordered
  nearest-neighbour lists.
- `SpatialEntityRepository::findStWithinMetres($geoJson, $metres, $criteria, $limit)`: the
  entities within a radius in metres, nearest first, measured on the spheroid and found
  through the column's GiST index.
- `SpatialEntityRepository::getGeometryField()` and `createStWithinMetresQueryBuilder()` for
  subclasses that write their own spatial queries.
- A prepended `doctrine.dbal.schema_filter` (`UtafitiLabsPostGISBundle::POSTGIS_SCHEMA_FILTER`)
  that keeps `spatial_ref_sys` and the `topology` schema out of `migrations:diff`, so the
  diff no longer drops `topology.topology_id_seq`. An application's own `schema_filter`
  replaces it.

### Fixed

- A `geography` column no longer churns in `migrations:diff`: introspection mapped
  `geography(Geometry,4326)` to the `geometry` type, so every diff re-declared the column.

## v0.1.0 — 2026-09-21

First release of the bundle under the utafiti tools: PostGIS geometry column
types (point, polygon, multipolygon, linestring), the spatial DQL functions,
the schema listener and the spatial repository base.
