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

namespace UtafitiLabs\PostGISBundle\Tests\Unit\Schema;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use UtafitiLabs\PostGISBundle\Schema\PostGISSchemaManager;
use UtafitiLabs\PostGISBundle\Schema\PostGISSchemaManagerFactory;

final class PostGISSchemaManagerFactoryTest extends TestCase
{
    public function testCreatesTypmodAwareSchemaManagerForPostgres(): void
    {
        // serverVersion pins the platform, so no connection is ever opened.
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'serverVersion' => '17',
        ]);

        $schemaManager = new PostGISSchemaManagerFactory()->createSchemaManager($connection);

        self::assertInstanceOf(PostGISSchemaManager::class, $schemaManager);
    }
}
