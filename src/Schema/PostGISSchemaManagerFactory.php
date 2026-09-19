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

namespace UtafitiLabs\PostGISBundle\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;

/**
 * Supplies the typmod-aware {@see PostGISSchemaManager} for PostgreSQL connections.
 * Register on the DBAL Configuration (standalone) or via `doctrine.dbal.schema_manager_factory`.
 */
final class PostGISSchemaManagerFactory implements SchemaManagerFactory
{
    /**
     * @return AbstractSchemaManager<PostgreSQLPlatform>
     */
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        $platform = $connection->getDatabasePlatform();
        \assert($platform instanceof PostgreSQLPlatform);

        return new PostGISSchemaManager($connection, $platform);
    }
}
