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
 * DQL: ST_KnnDistance(a, b) — the PostGIS `<->` distance operator, for
 * index-ordered nearest-neighbour lists (DQL has no operator syntax for it):
 *
 *   SELECT e FROM … e ORDER BY ST_KnnDistance(e.geom, ST_GeomFromGeoJSON(:p)) ASC   -- with a LIMIT
 *
 * An ordering key, not a measurement: on geometry it is planar CRS units
 * (degrees for EPSG:4326), so the order is planar too. Select ST_Distance on
 * geography when the number has to be metres.
 *
 * PostGIS: "Used in the "ORDER BY" clause provides index-assisted
 * nearest-neighbor result sets." — https://postgis.net/docs/geometry_distance_knn.html
 */
final class StKnnDistance extends AbstractSpatialFunction
{
    protected function functionName(): string
    {
        return '<->';
    }

    protected function minArgs(): int
    {
        return 2;
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        [$left, $right] = array_map(
            static fn (Node|string $node): string => $node instanceof Node ? $node->dispatch($sqlWalker) : $node,
            $this->arguments,
        );

        return \sprintf('(%s <-> %s)', $left, $right);
    }
}
