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

namespace UtafitiLabs\PostGISBundle\Platform;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Index;

/**
 * PostgreSQL platform that renders spatial indexes with `USING gist`.
 *
 * DBAL 4 ignores index flags when generating `CREATE INDEX`, so a geometry column
 * would otherwise get a default (btree) index — useless for spatial queries. The
 * SpatialSchemaListener marks geometry indexes with the `spatial` flag; this platform
 * turns that into a GiST index. Wired into a connection via {@see PostGISMiddleware}.
 */
class PostGISPlatform extends PostgreSQLPlatform
{
    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $sql = parent::getCreateIndexSQL($index, $table);

        if ($index->hasFlag('spatial')) {
            // "CREATE INDEX name ON <table> (cols)" -> "... ON <table> USING gist (cols)"
            $spatial = preg_replace('/ ON (.+?) \(/', ' ON $1 USING gist (', $sql, 1);

            return $spatial ?? $sql;
        }

        return $sql;
    }
}
