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

/**
 * The 0.3 function set exists so consumers can express DISSOLVE-style
 * aggregation (union → repair → simplify → extract polygons → force multi) and
 * geodesic measurement entirely in DQL — no raw SQL in application code.
 */
final class DissolveFunctionsIntegrationTest extends PostGISIntegrationTestCase
{
    private const string SQUARE_A = '{"type":"Polygon","coordinates":[[[36,-4],[36.5,-4],[36.5,-3],[36,-3],[36,-4]]]}';
    private const string SQUARE_B = '{"type":"Polygon","coordinates":[[[36.5,-4],[37,-4],[37,-3],[36.5,-3],[36.5,-4]]]}';

    private function persistSquares(): void
    {
        foreach ([self::SQUARE_A, self::SQUARE_B] as $geoJson) {
            $thing = new SpatialThing();
            $thing->geom = $geoJson;
            $this->em->persist($thing);
        }
        $this->em->flush();
    }

    public function testTheDissolveChainComposesInDql(): void
    {
        $this->persistSquares();

        // Two adjacent squares dissolve into ONE MultiPolygon with one part.
        $json = $this->em->createQuery(\sprintf(
            'SELECT ST_AsGeoJSON(ST_Multi(ST_CollectionExtract(ST_MakeValid(
                 ST_SimplifyPreserveTopology(ST_Union(t.geom), :tolerance)), 3))) FROM %s t',
            SpatialThing::class,
        ))->setParameter('tolerance', 0.0001)->getSingleScalarResult();

        self::assertIsString($json);
        /** @var array{type: string, coordinates: list<mixed>} $decoded */
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('MultiPolygon', $decoded['type']);
        self::assertCount(1, $decoded['coordinates'], 'adjacent squares must dissolve into one part');
    }

    public function testStAreaOnGeographyMeasuresGeodesicSquareMetres(): void
    {
        $this->persistSquares();

        // The two squares together span 1°×1° near the equator ≈ 12,300 km².
        $area = $this->em->createQuery(\sprintf(
            'SELECT ST_Area(Geography(ST_Union(t.geom))) FROM %s t',
            SpatialThing::class,
        ))->getSingleScalarResult();

        self::assertIsNumeric($area);
        $km2 = (float) $area / 1e6;
        self::assertGreaterThan(11_000, $km2);
        self::assertLessThan(13_500, $km2);
    }

    public function testStAreaOnGeometryIsPlanarDegrees(): void
    {
        $this->persistSquares();

        $area = $this->em->createQuery(
            \sprintf('SELECT ST_Area(ST_Union(t.geom)) FROM %s t', SpatialThing::class),
        )->getSingleScalarResult();

        self::assertIsNumeric($area);
        self::assertEqualsWithDelta(1.0, (float) $area, 0.001, '1°×1° in planar degrees²');
    }
}
