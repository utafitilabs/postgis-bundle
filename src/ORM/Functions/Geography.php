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

use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\SqlWalker;

/**
 * DQL: Geography(g) — casts a geometry to geography, so measurement functions
 * return geodesic metres instead of planar CRS units:
 *
 *   SELECT ST_Area(Geography(t.geom)) …   -- m² on the spheroid
 *
 * (DQL has no PostgreSQL `::geography` cast syntax; this function is that cast.)
 */
final class Geography extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return 'CAST';
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $argument = $this->arguments[0];

        return \sprintf(
            'CAST(%s AS geography)',
            $argument instanceof Node ? $argument->dispatch($sqlWalker) : $argument,
        );
    }
}
