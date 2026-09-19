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
 * DQL: ST_Area(g) — area of a geometry. On geometry the unit is planar
 * (squared CRS units — degrees² for EPSG:4326!); wrap in Geography() for
 * geodesic square metres: ST_Area(Geography(t.geom)).
 */
final class StArea extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_Area';
    }
}
