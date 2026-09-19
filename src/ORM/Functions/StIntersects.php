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
 * DQL: ST_Intersects(a, b) — true when the two geometries spatially intersect.
 *
 * Use in a WHERE predicate, e.g.
 *   WHERE ST_Intersects(e.geom, ST_MakeEnvelope(:minX, :minY, :maxX, :maxY, 4326)) = true
 */
final class StIntersects extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_Intersects';
    }

    protected function minArgs(): int
    {
        return 2;
    }
}
