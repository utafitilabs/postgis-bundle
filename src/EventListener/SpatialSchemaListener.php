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

namespace UtafitiLabs\PostGISBundle\EventListener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use UtafitiLabs\PostGISBundle\Types\GeometryType;

/**
 * Adds a GiST index for every geometry/geography column when the schema is
 * generated (ORM `postGenerateSchema`). The index is marked with the `spatial`
 * flag; {@see \UtafitiLabs\PostGISBundle\Platform\PostGISPlatform} turns that into
 * `USING gist` (DBAL 4 ignores the flag on its own).
 *
 * Result: consumers get correct spatial indexes from `doctrine:migrations:diff`
 * and `schema:create` without hand-writing any DDL.
 */
final class SpatialSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        foreach ($args->getSchema()->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                if (!$column->getType() instanceof GeometryType) {
                    continue;
                }

                // Keep well under Postgres's 63-char identifier limit.
                $indexName = substr(\sprintf('idx_%s_%s_sp', $table->getName(), $column->getName()), 0, 63);

                if (!$table->hasIndex($indexName)) {
                    $table->addIndex([$column->getName()], $indexName, ['spatial']);
                }
            }
        }
    }
}
