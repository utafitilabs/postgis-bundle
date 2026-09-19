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
 * DQL: ST_AsGeoJSON(geom [, maxdecimaldigits [, options]]) — GeoJSON text of a geometry.
 */
final class StAsGeoJson extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_AsGeoJSON';
    }

    protected function maxArgs(): int
    {
        return 3;
    }
}
