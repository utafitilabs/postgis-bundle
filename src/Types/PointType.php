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

/** `geometry(Point, 4326)`. */
final class PointType extends GeometryType
{
    public const string NAME = 'point';

    protected function defaultGeometryType(): string
    {
        return 'POINT';
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [];
    }
}
