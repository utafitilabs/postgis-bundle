# Changelog

All notable changes to `utafitilabs/postgis-bundle` are documented here.

## Contents

- [v0.1.1](#v011--2026-11-17)
- [v0.1.0](#v010--2026-09-21)

## v0.1.1 — 2026-11-17

A typed geometry column (`geometry(MultiPolygon,4326)` and its kin) is read back
on DBAL 4.6. Introspection handed the column a type instance, and DBAL 4.6 can no
longer find the name of a type its registry serves as a service: schema tools
failed with "Type of the class MultiPolygonType is not registered". The column
is now given its type by name where DBAL has `Column::setTypeName()` (4.6 and
later) and by instance on older DBAL 4.

## v0.1.0 — 2026-09-21

First release of the bundle under the utafiti tools: PostGIS geometry column
types (point, polygon, multipolygon, linestring), the spatial DQL functions,
the schema listener and the spatial repository base.
