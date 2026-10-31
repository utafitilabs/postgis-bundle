<?php

declare(strict_types=1);

/*
 * This file is part of the UtafitiLabs PostGIS Bundle.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace UtafitiLabs\PostGISBundle\ORM\Functions;

/**
 * DQL: ST_DWithin(a, b, distance[, use_spheroid]) — true when the two are
 * within the given distance of each other. With plain geometries the distance
 * is in the SRID's units; wrap both sides in Geography(...) to make it metres:
 *
 *   WHERE ST_DWithin(Geography(e.geom), Geography(ST_GeomFromGeoJSON(:p)), :meters) = true
 *
 * The GiST index the bundle creates on every spatial column serves ST_DWithin
 * on the column as declared. Geography(...) around a geometry column hides
 * it from that index, so on a large table pair the geodesic test with a planar
 * one on the column itself, as SpatialEntityRepository::findStWithinMetres() does.
 * PostGIS: "This function call includes a bounding box comparison that makes use
 * of any indexes that are available on the geometries." —
 * https://postgis.net/docs/ST_DWithin.html
 */
final class StDWithin extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_DWithin';
    }

    protected function minArgs(): int
    {
        return 3;
    }

    protected function maxArgs(): int
    {
        return 4;
    }
}
