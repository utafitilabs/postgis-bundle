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

namespace UtafitiLabs\PostGISBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\Dbal\RegexSchemaAssetFilter;
use Doctrine\ORM\Tools\SchemaTool;
use UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle;

/**
 * After CREATE EXTENSION, the schema diff (what doctrine:migrations:diff
 * generates) must leave PostGIS's own tables and sequences alone.
 */
final class SchemaFilterTest extends PostGISIntegrationTestCase
{
    private bool $topologyInstalled = false;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->em->getConnection();
        $connection->executeStatement('CREATE EXTENSION IF NOT EXISTS postgis');
        if (false === $connection->fetchOne("SELECT 1 FROM pg_available_extensions WHERE name = 'postgis_topology'")) {
            return;
        }
        $this->topologyInstalled = false === $connection->fetchOne("SELECT 1 FROM pg_extension WHERE extname = 'postgis_topology'");
        $connection->executeStatement('CREATE EXTENSION IF NOT EXISTS postgis_topology');
    }

    protected function tearDown(): void
    {
        if ($this->topologyInstalled) {
            $this->em->getConnection()->executeStatement('DROP EXTENSION postgis_topology');
        }

        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function diffSql(): array
    {
        $connection = $this->em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $to = (new SchemaTool($this->em))->getSchemaFromMetadata($this->em->getMetadataFactory()->getAllMetadata());

        return $connection->getDatabasePlatform()->getAlterSchemaSQL(
            $schemaManager->createComparator()->compareSchemas($schemaManager->introspectSchema(), $to),
        );
    }

    /**
     * @param list<string> $sql
     *
     * @return list<string>
     */
    private static function touchingPostGIS(array $sql): array
    {
        return array_values(array_filter(
            $sql,
            static fn (string $statement): bool => str_contains($statement, 'spatial_ref_sys') || str_contains($statement, 'topology'),
        ));
    }

    public function testWithoutTheFilterTheDiffDropsTheTopologySequence(): void
    {
        if (!$this->hasTopology()) {
            self::markTestSkipped('postgis_topology is not available on this server.');
        }

        self::assertContains('DROP SEQUENCE topology.topology_id_seq CASCADE', $this->diffSql());
    }

    public function testWithTheBundleFilterTheDiffLeavesPostGISAlone(): void
    {
        $this->em->getConnection()->getConfiguration()->setSchemaAssetsFilter(
            new RegexSchemaAssetFilter(UtafitiLabsPostGISBundle::POSTGIS_SCHEMA_FILTER),
        );

        $sql = $this->diffSql();

        self::assertSame([], self::touchingPostGIS($sql), implode(' | ', $sql));
    }

    private function hasTopology(): bool
    {
        return false !== $this->em->getConnection()->fetchOne("SELECT 1 FROM pg_extension WHERE extname = 'postgis_topology'");
    }
}
