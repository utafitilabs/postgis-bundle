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
 * DQL: ST_Intersection(a, b[, gridSize]) — the part two shapes share, e.g. the
 * stretch of a line inside a polygon, or the overlap of two polygons.
 *
 * PostGIS: "Returns a geometry representing the point-set intersection of two
 * geometries." — https://postgis.net/docs/ST_Intersection.html
 */
final class StIntersection extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_Intersection';
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
