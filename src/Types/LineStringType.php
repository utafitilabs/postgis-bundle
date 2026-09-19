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

use Doctrine\DBAL\Platforms\AbstractPlatform;

/** `geometry(LineString, 4326)`. */
final class LineStringType extends GeometryType
{
    public const string NAME = 'linestring';

    protected function defaultGeometryType(): string
    {
        return 'LINESTRING';
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [];
    }
}
