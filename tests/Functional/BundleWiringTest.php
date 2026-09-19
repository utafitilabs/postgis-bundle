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

namespace UtafitiLabs\PostGISBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use UtafitiLabs\PostGISBundle\Platform\PostGISPlatform;
use UtafitiLabs\PostGISBundle\Schema\PostGISSchemaManager;

/**
 * Boots a real Symfony kernel with only FrameworkBundle + DoctrineBundle +
 * UtafitiLabsPostGISBundle and asserts the bundle wired everything: the spatial
 * types, the middleware (PostGISPlatform), and the schema-manager factory.
 */
final class BundleWiringTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $url = getenv('DATABASE_URL');
        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set DATABASE_URL to run the bundle functional test.');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Symfony's kernel registers an exception handler on boot and does not restore
        // it; clean up that one so PHPUnit's strict risky check stays green.
        restore_exception_handler();
    }

    public function testBundleRegistersSpatialTypes(): void
    {
        self::bootKernel(['debug' => false]);
        self::connection(); // instantiating the connection registers the configured types

        self::assertTrue(Type::hasType('geometry'), 'generic type registered by the bundle');
        self::assertTrue(Type::hasType('multipolygon'), 'typed sub-type registered by the bundle');
    }

    public function testMiddlewareYieldsPostGISPlatform(): void
    {
        self::bootKernel(['debug' => false]);

        self::assertInstanceOf(PostGISPlatform::class, self::connection()->getDatabasePlatform());
    }

    public function testSchemaManagerFactoryYieldsPostGISSchemaManager(): void
    {
        self::bootKernel(['debug' => false]);

        self::assertInstanceOf(PostGISSchemaManager::class, self::connection()->createSchemaManager());
    }

    private static function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }
}
