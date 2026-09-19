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
 * DQL: ST_CollectionExtract(g, type) — keeps only components of the given type
 * from a collection (1 = points, 2 = lines, 3 = polygons).
 */
final class StCollectionExtract extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'ST_CollectionExtract';
    }

    protected function minArgs(): int
    {
        return 2;
    }
}
