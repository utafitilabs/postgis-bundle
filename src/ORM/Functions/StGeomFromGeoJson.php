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
 * DQL: ST_GeomFromGeoJSON(text) — builds a geometry (SRID 4326) from GeoJSON text.
 */
final class StGeomFromGeoJson extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_GeomFromGeoJSON';
    }
}
