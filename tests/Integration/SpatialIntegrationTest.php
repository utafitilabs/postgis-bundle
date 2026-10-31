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

use UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures\SpatialThing;

final class SpatialIntegrationTest extends PostGISIntegrationTestCase
{
    public function testGeometryColumnGetsAGistIndex(): void
    {
        $indexdef = $this->em->getConnection()->fetchOne(
            "SELECT indexdef FROM pg_indexes WHERE tablename = 'spatial_thing' AND indexdef ILIKE '%using gist%'",
        );

        self::assertIsString($indexdef, 'Expected an auto-generated GiST index on the geometry column');
    }

    public function testGeoJsonRoundTrip(): void
    {
        $thing = new SpatialThing();
        $thing->geom = '{"type":"Point","coordinates":[36.5,-3.2]}';
        $this->em->persist($thing);
        $this->em->flush();
        $id = $thing->id;
        $this->em->clear();

        $loaded = $this->em->find(SpatialThing::class, $id);
        self::assertNotNull($loaded);

        /** @var array{type: string, coordinates: array<int, float>} $decoded */
        $decoded = json_decode((string) $loaded->geom, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Point', $decoded['type']);
        self::assertEqualsWithDelta(36.5, $decoded['coordinates'][0], 1e-6);
        self::assertEqualsWithDelta(-3.2, $decoded['coordinates'][1], 1e-6);
    }

    public function testStAsGeoJsonAcceptsOptionalPrecisionArgument(): void
    {
        $thing = new SpatialThing();
        $thing->geom = '{"type":"Point","coordinates":[36.512345,-3.212345]}';
        $this->em->persist($thing);
        $this->em->flush();

        $json = $this->em->createQuery(
            \sprintf('SELECT ST_AsGeoJSON(t.geom, 2) FROM %s t WHERE t.id = :id', SpatialThing::class),
        )->setParameter('id', $thing->id)->getSingleScalarResult();

        self::assertIsString($json);

        /** @var array{type: string, coordinates: array<int, float>} $decoded */
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Point', $decoded['type']);
        self::assertEqualsWithDelta(36.51, $decoded['coordinates'][0], 1e-9);
        self::assertEqualsWithDelta(-3.21, $decoded['coordinates'][1], 1e-9);
    }

    public function testStIntersectsFiltersInDql(): void
    {
        $thing = new SpatialThing();
        $thing->geom = '{"type":"Point","coordinates":[36.5,-3.2]}';
        $this->em->persist($thing);
        $this->em->flush();

        $polygon = '{"type":"Polygon","coordinates":[[[36,-4],[37,-4],[37,-3],[36,-3],[36,-4]]]}';

        $count = $this->em->createQuery(
            \sprintf(
                'SELECT COUNT(t.id) FROM %s t WHERE ST_Intersects(t.geom, ST_GeomFromGeoJSON(:poly)) = true',
                SpatialThing::class,
            ),
        )->setParameter('poly', $polygon)->getSingleScalarResult();

        self::assertEquals(1, $count);
    }

    public function testStDWithinFiltersByGeodesicDistanceInDql(): void
    {
        $thing = new SpatialThing();
        $thing->geom = '{"type":"Point","coordinates":[36.5,-3.2]}';
        $this->em->persist($thing);
        $this->em->flush();

        // ~111 m east of the stored point; geography casts make metres metres.
        $near = '{"type":"Point","coordinates":[36.501,-3.2]}';
        $countWithin = fn (float $meters): mixed => $this->em->createQuery(
            \sprintf(
                'SELECT COUNT(t.id) FROM %s t'
                .' WHERE ST_DWithin(Geography(t.geom), Geography(ST_GeomFromGeoJSON(:p)), :m) = true',
                SpatialThing::class,
            ),
        )->setParameter('p', $near)->setParameter('m', $meters)->getSingleScalarResult();

        self::assertEquals(1, $countWithin(150.0));
        self::assertEquals(0, $countWithin(50.0));
    }

    public function testStDistanceOnGeographyReturnsMetresAndOrdersRows(): void
    {
        foreach (['far' => '[0.01,0]', 'origin' => '[0,0]', 'near' => '[0.001,0]'] as $label => $coordinates) {
            $thing = new SpatialThing();
            $thing->geom = '{"type":"Point","coordinates":'.$coordinates.'}';
            $thing->label = $label;
            $this->em->persist($thing);
        }
        $this->em->flush();

        /** @var list<array{label: string, metres: float|string}> $rows */
        $rows = $this->em->createQuery(\sprintf(
            'SELECT t.label AS label, ST_Distance(Geography(t.geom), ST_GeomFromGeoJSON(:p)) AS metres'
            .' FROM %s t ORDER BY metres ASC',
            SpatialThing::class,
        ))->setParameter('p', '{"type":"Point","coordinates":[0,0]}')->getArrayResult();

        self::assertSame(['origin', 'near', 'far'], array_column($rows, 'label'));
        self::assertEqualsWithDelta(0.0, (float) $rows[0]['metres'], 1e-6);
        self::assertEqualsWithDelta(111.32, (float) $rows[1]['metres'], 0.5, '0.001° of longitude on the equator');
        self::assertEqualsWithDelta(1113.2, (float) $rows[2]['metres'], 5.0);
    }

    public function testStDistanceOnGeometryIsPlanarDegrees(): void
    {
        $thing = new SpatialThing();
        $thing->geom = '{"type":"Point","coordinates":[0,0]}';
        $this->em->persist($thing);
        $this->em->flush();

        $distance = $this->em->createQuery(\sprintf(
            'SELECT ST_Distance(t.geom, ST_GeomFromGeoJSON(:p)) FROM %s t',
            SpatialThing::class,
        ))->setParameter('p', '{"type":"Point","coordinates":[3,4]}')->getSingleScalarResult();

        self::assertEqualsWithDelta(5.0, (float) $distance, 1e-9);
    }

    public function testStLengthOnGeographyReturnsMetres(): void
    {
        $line = new SpatialThing();
        $line->geom = '{"type":"LineString","coordinates":[[0,0],[0.01,0]]}';
        $this->em->persist($line);
        $this->em->flush();

        $query = fn (string $dql): mixed => $this->em->createQuery(\sprintf($dql, SpatialThing::class))
            ->getSingleScalarResult();

        self::assertEqualsWithDelta(1113.2, (float) $query('SELECT ST_Length(Geography(t.geom)) FROM %s t'), 5.0);
        self::assertEqualsWithDelta(0.01, (float) $query('SELECT ST_Length(t.geom) FROM %s t'), 1e-9, 'planar degrees');
    }

    public function testStKnnDistanceOrdersByTheIndexDistanceOperator(): void
    {
        foreach (['far' => '[2,0]', 'near' => '[1,0]', 'nearest' => '[0.5,0]'] as $label => $coordinates) {
            $thing = new SpatialThing();
            $thing->geom = '{"type":"Point","coordinates":'.$coordinates.'}';
            $thing->label = $label;
            $this->em->persist($thing);
        }
        $this->em->flush();

        $query = $this->em->createQuery(\sprintf(
            'SELECT t.label FROM %s t ORDER BY ST_KnnDistance(t.geom, ST_GeomFromGeoJSON(:p)) ASC',
            SpatialThing::class,
        ))->setParameter('p', '{"type":"Point","coordinates":[0,0]}')->setMaxResults(2);

        $sql = $query->getSQL();
        self::assertIsString($sql);
        self::assertStringContainsString(' <-> ', $sql);
        self::assertSame(['nearest', 'near'], array_column($query->getArrayResult(), 'label'));
    }

    public function testStBufferOnGeographyBuffersByMetres(): void
    {
        $thing = new SpatialThing();
        $thing->geom = '{"type":"Point","coordinates":[0,0]}';
        $this->em->persist($thing);
        $this->em->flush();

        $area = $this->em->createQuery(\sprintf(
            'SELECT ST_Area(ST_Buffer(Geography(t.geom), :metres)) FROM %s t',
            SpatialThing::class,
        ))->setParameter('metres', 100.0)->getSingleScalarResult();

        self::assertEqualsWithDelta(\M_PI * 100 ** 2, (float) $area, 0.01 * \M_PI * 100 ** 2, 'a 100 m disc');
    }

    public function testStIntersectionReturnsTheSharedPart(): void
    {
        $thing = new SpatialThing();
        $thing->geom = '{"type":"Polygon","coordinates":[[[0,0],[2,0],[2,2],[0,2],[0,0]]]}';
        $this->em->persist($thing);
        $this->em->flush();

        $area = $this->em->createQuery(\sprintf(
            'SELECT ST_Area(ST_Intersection(t.geom, ST_GeomFromGeoJSON(:other))) FROM %s t',
            SpatialThing::class,
        ))->setParameter('other', '{"type":"Polygon","coordinates":[[[1,1],[3,1],[3,3],[1,3],[1,1]]]}')
            ->getSingleScalarResult();

        self::assertEqualsWithDelta(1.0, (float) $area, 1e-9, 'the overlapping 1x1 square');
    }
}
