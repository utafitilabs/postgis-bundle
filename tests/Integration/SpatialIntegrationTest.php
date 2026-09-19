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
}
