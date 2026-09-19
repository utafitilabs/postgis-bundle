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

/**
 * `geometry(MultiPolygon, 4326)` — PostGIS enforces the shape via the typmod.
 * Diffs stay clean thanks to {@see \UtafitiLabs\PostGISBundle\Schema\PostGISSchemaManager}.
 */
final class MultiPolygonType extends GeometryType
{
    public const string NAME = 'multipolygon';

    protected function defaultGeometryType(): string
    {
        return 'MULTIPOLYGON';
    }

    /** Only the generic `geometry` type maps the DB type; the SchemaManager refines. */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [];
    }
}
