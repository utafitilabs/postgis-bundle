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

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * DBAL middleware that makes a PostgreSQL connection use {@see PostGISPlatform},
 * so spatial indexes render with `USING gist`. Register it on the DBAL
 * Configuration (standalone) or via the `doctrine.middleware` tag (Symfony).
 */
final class PostGISMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
            {
                return new PostGISPlatform();
            }
        };
    }
}
