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

use Doctrine\ORM\Tools\SchemaTool;
use UtafitiLabs\PostGISBundle\Types\GeometryType;
use UtafitiLabs\PostGISBundle\Types\MultiPolygonType;

final class SchemaDiffTest extends PostGISIntegrationTestCase
{
    public function testTypedColumnGetsTheTypmod(): void
    {
        $ft = $this->em->getConnection()->fetchOne(
            "SELECT format_type(a.atttypid, a.atttypmod)
             FROM pg_attribute a JOIN pg_class c ON a.attrelid = c.oid
             WHERE c.relname = 'typed_shape' AND a.attname = 'area'",
        );

        self::assertSame('geometry(MultiPolygon,4326)', $ft);
    }

    public function testIntrospectionRecoversTheTypedColumn(): void
    {
        // The churn fix: the DB-side column must resolve to the SAME type the
        // entity declares. If introspection returns MultiPolygonType, the diff matches.
        $column = $this->em->getConnection()->createSchemaManager()
            ->introspectTable('typed_shape')->getColumn('area');

        self::assertInstanceOf(MultiPolygonType::class, $column->getType());
    }

    public function testGenericColumnStaysGeneric(): void
    {
        $column = $this->em->getConnection()->createSchemaManager()
            ->introspectTable('spatial_thing')->getColumn('geom');

        self::assertInstanceOf(GeometryType::class, $column->getType());
        self::assertNotInstanceOf(MultiPolygonType::class, $column->getType());
    }

    public function testTypedColumnDoesNotChurn(): void
    {
        $conn = $this->em->getConnection();
        $sm = $conn->createSchemaManager();

        $from = $sm->introspectSchema();
        $to = (new SchemaTool($this->em))
            ->getSchemaFromMetadata($this->em->getMetadataFactory()->getAllMetadata());

        $diff = $sm->createComparator()->compareSchemas($from, $to);
        $sql = $conn->getDatabasePlatform()->getAlterSchemaSQL($diff);

        $touching = array_values(array_filter(
            $sql,
            static fn (string $s): bool => str_contains($s, 'typed_shape'),
        ));

        self::assertSame([], $touching, 'Typed column churned: '.implode(' | ', $touching));
    }
}
