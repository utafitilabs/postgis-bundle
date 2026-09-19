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

namespace UtafitiLabs\PostGISBundle\Types;

/**
 * PostGIS `geography` column (spheroidal calculations), exchanged as GeoJSON text.
 *
 * Identical contract to {@see GeometryType}; only the storage keyword and the write
 * cast differ (GeoJSON parses to geometry, then casts to geography).
 */
final class GeographyType extends GeometryType
{
    public const string NAME = 'geography';

    protected function columnType(): string
    {
        return 'geography';
    }

    protected function fromGeoJsonSql(string $sqlExpr): string
    {
        return \sprintf('ST_GeomFromGeoJSON(%s)::geography', $sqlExpr);
    }
}
