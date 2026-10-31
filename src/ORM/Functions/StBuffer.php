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
 * DQL: ST_Buffer(g, radius[, style]) — the area within the radius of a shape.
 * On geometry the radius is in CRS units (degrees for EPSG:4326!); on
 * geography it is metres: ST_Buffer(Geography(t.geom), :metres).
 *
 * PostGIS: "For geography, the distance is specified in meters." —
 * https://postgis.net/docs/ST_Buffer.html
 */
final class StBuffer extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_Buffer';
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
