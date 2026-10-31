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
 * DQL: ST_Length(g[, use_spheroid]) — length of a linear geometry. On geometry
 * the unit is planar (CRS units — degrees for EPSG:4326!); wrap in Geography()
 * for geodesic metres: ST_Length(Geography(t.geom)).
 *
 * PostGIS: for geography, "Units of length are in meters." —
 * https://postgis.net/docs/ST_Length.html
 */
final class StLength extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_Length';
    }

    protected function maxArgs(): int
    {
        return 2;
    }
}
