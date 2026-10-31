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
 * DQL: ST_Distance(a, b[, use_spheroid]) — minimum distance between two shapes.
 * On geometry the unit is planar (CRS units — degrees for EPSG:4326!); with a
 * geography argument it is geodesic metres, so it can be selected and ordered by:
 *
 *   SELECT e, ST_Distance(Geography(e.geom), ST_GeomFromGeoJSON(:p)) AS HIDDEN metres
 *   … ORDER BY metres ASC
 *
 * PostGIS: "For geography types defaults to return the minimum geodesic distance
 * between two geographies in meters" — https://postgis.net/docs/ST_Distance.html
 * (the geometry side is cast to geography implicitly).
 */
final class StDistance extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_Distance';
    }

    protected function minArgs(): int
    {
        return 2;
    }

    protected function maxArgs(): int
    {
        return 3;
    }
}
