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

use Doctrine\DBAL\Exception\DriverException;
use UtafitiLabs\PostGISBundle\Exception\MissingSpatialColumnException;
use UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures\GeographyPlace;
use UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures\GeographyPlaceRepository;
use UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures\PlainThingRepository;
use UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures\SingleManagerRegistry;
use UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures\SpatialThing;
use UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures\SpatialThingRepository;

/**
 * The repository base class: every method carries the St signature marker, the
 * geometry column is discovered from Doctrine metadata, and an entity WITHOUT
 * one is rejected at construction with a teaching message.
 */
final class SpatialEntityRepositoryTest extends PostGISIntegrationTestCase
{
    private const string INSIDE = '{"type":"Point","coordinates":[36.5,-3.5]}';
    private const string OUTSIDE = '{"type":"Point","coordinates":[10.0,10.0]}';
    private const string SQUARE = '{"type":"Polygon","coordinates":[[[36,-4],[37,-4],[37,-3],[36,-3],[36,-4]]]}';

    private function repository(): SpatialThingRepository
    {
        return new SpatialThingRepository(new SingleManagerRegistry($this->em));
    }

    private function persistThing(string $geoJson, ?string $label = null): SpatialThing
    {
        $thing = new SpatialThing();
        $thing->geom = $geoJson;
        $thing->label = $label;
        $this->em->persist($thing);
        $this->em->flush();

        return $thing;
    }

    public function testAnEntityWithoutAGeometryColumnIsRejectedAtConstruction(): void
    {
        $this->expectException(MissingSpatialColumnException::class);
        $this->expectExceptionMessageMatches('/PlainThing/');
        $this->expectExceptionMessageMatches('/geometry/');

        new PlainThingRepository(new SingleManagerRegistry($this->em));
    }

    public function testFindStIntersectingReturnsOnlyIntersectingEntities(): void
    {
        $inside = $this->persistThing(self::INSIDE);
        $this->persistThing(self::OUTSIDE);

        $found = $this->repository()->findStIntersecting(self::SQUARE);

        self::assertCount(1, $found);
        self::assertSame($inside->id, $found[0]->id);
    }

    public function testFindStIntersectingNarrowsWithOrdinaryCriteria(): void
    {
        $this->persistThing(self::INSIDE, 'keep');
        $this->persistThing(self::INSIDE, 'drop');

        $found = $this->repository()->findStIntersecting(self::SQUARE, ['label' => 'keep']);

        self::assertCount(1, $found);
        self::assertSame('keep', $found[0]->label);
    }

    public function testStAreaKm2MeasuresGeodesicArea(): void
    {
        // Two half-degree-wide squares spanning 1°×1° at the equator ≈ 12,300 km².
        $this->persistThing('{"type":"Polygon","coordinates":[[[36,-4],[36.5,-4],[36.5,-3],[36,-3],[36,-4]]]}', 'a');
        $this->persistThing('{"type":"Polygon","coordinates":[[[36.5,-4],[37,-4],[37,-3],[36.5,-3],[36.5,-4]]]}', 'b');

        $km2 = $this->repository()->stAreaKm2();

        self::assertGreaterThan(11_000, $km2);
        self::assertLessThan(13_500, $km2);
    }

    public function testStAreaKm2NarrowsWithCriteriaAndIsZeroWithoutRows(): void
    {
        $this->persistThing('{"type":"Polygon","coordinates":[[[36,-4],[36.5,-4],[36.5,-3],[36,-3],[36,-4]]]}', 'a');
        $this->persistThing('{"type":"Polygon","coordinates":[[[36.5,-4],[37,-4],[37,-3],[36.5,-3],[36.5,-4]]]}', 'b');

        $half = $this->repository()->stAreaKm2(['label' => 'a']);
        self::assertGreaterThan(5_500, $half);
        self::assertLessThan(6_750, $half);

        self::assertSame(0.0, $this->repository()->stAreaKm2(['label' => 'nothing-has-this']));
    }

    public function testAnUnknownCriteriaFieldThrowsAHelpfulError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nope/');

        $this->repository()->stAreaKm2(['nope' => 1]);
    }

    private static function point(float $longitude, float $latitude): string
    {
        return \sprintf('{"type":"Point","coordinates":[%F,%F]}', $longitude, $latitude);
    }

    /**
     * @param list<SpatialThing> $things
     *
     * @return list<string|null>
     */
    private static function labels(array $things): array
    {
        return array_map(static fn (SpatialThing $thing): ?string => $thing->label, $things);
    }

    public function testSubclassesReachTheDiscoveredGeometryField(): void
    {
        self::assertSame('geom', $this->repository()->geometryField());
    }

    public function testFindStWithinMetresReturnsTheRowsInsideTheRadiusNearestFirst(): void
    {
        // 0.001° of longitude on the equator is about 111 m.
        $this->persistThing(self::point(0.002, 0), '222 m');
        $this->persistThing(self::point(0.0005, 0), '56 m');
        $this->persistThing(self::point(0.01, 0), '1113 m');
        $this->persistThing(self::point(0.001, 0), '111 m');

        $found = $this->repository()->findStWithinMetres(self::point(0, 0), 300.0);

        self::assertSame(['56 m', '111 m', '222 m'], self::labels($found));
    }

    public function testFindStWithinMetresNarrowsWithCriteriaAndKeepsTheNearestUpToTheLimit(): void
    {
        $this->persistThing(self::point(0.002, 0), 'keep');
        $this->persistThing(self::point(0.001, 0), 'keep');
        $this->persistThing(self::point(0.0005, 0), 'drop');
        $this->persistThing(self::point(0.0015, 0), 'keep');

        $found = $this->repository()->findStWithinMetres(self::point(0, 0), 300.0, ['label' => 'keep'], 2);

        $longitudes = array_map(static function (SpatialThing $thing): float {
            /** @var array{coordinates: array{0: float, 1: float}} $point */
            $point = json_decode((string) $thing->geom, true, 512, \JSON_THROW_ON_ERROR);

            return round($point['coordinates'][0], 6);
        }, $found);

        self::assertSame([0.001, 0.0015], $longitudes, 'the two nearest of the three kept rows');
    }

    public function testFindStWithinMetresMeasuresOnTheSpheroidAtHighLatitudes(): void
    {
        // At 70° north 0.005° of longitude is about 190 m, far less than at the equator.
        $this->persistThing(self::point(10.005, 70), 'inside');
        $this->persistThing(self::point(10.01, 70), 'outside');

        $found = $this->repository()->findStWithinMetres(self::point(10, 70), 300.0);

        self::assertSame(['inside'], self::labels($found));
    }

    public function testFindStWithinMetresFindsRowsAcrossTheAntimeridianAndNearThePoles(): void
    {
        $this->persistThing(self::point(-179.9995, 0), 'across');
        $this->persistThing(self::point(90, 89.999), 'polar');

        self::assertSame(['across'], self::labels($this->repository()->findStWithinMetres(self::point(179.9995, 0), 200.0)));
        self::assertSame(['polar'], self::labels($this->repository()->findStWithinMetres(self::point(-90, 89.999), 300.0)));
    }

    public function testFindStWithinMetresUsesTheGistIndexOfAGeometryColumn(): void
    {
        $sql = $this->repository()->withinMetresQueryBuilder(self::point(0, 0), 300.0)->getQuery()->getSQL();
        self::assertIsString($sql);

        // GENERIC_PLAN (PostgreSQL 16+) plans the statement with its placeholders unbound.
        $placeholder = 0;
        $numbered = preg_replace_callback('/\?/', static function () use (&$placeholder): string {
            return '$'.++$placeholder;
        }, $sql);

        // Sent as a simple query: unbound $n placeholders are refused by a server-side prepare.
        $pdo = $this->em->getConnection()->getNativeConnection();
        self::assertInstanceOf(\PDO::class, $pdo);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        $pdo->exec('SET enable_seqscan = off');
        $statement = $pdo->query('EXPLAIN (GENERIC_PLAN) '.$numbered);
        self::assertInstanceOf(\PDOStatement::class, $statement);
        /** @var list<string> $lines */
        $lines = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $plan = implode("\n", $lines);
        $pdo->exec('RESET enable_seqscan');

        self::assertMatchesRegularExpression('/Index Scan|Bitmap Index Scan/', $plan, $plan);
    }

    public function testTheRadiusQueryBuilderReturnsTheDistanceWhenSelected(): void
    {
        $this->persistThing(self::point(0.002, 0), 'far');
        $this->persistThing(self::point(0.001, 0), 'near');

        $repository = $this->repository();
        /** @var list<array{0: SpatialThing, metres: mixed}> $rows */
        $rows = $repository->withinMetresQueryBuilder(self::point(0, 0), 300.0)
            ->addSelect(\sprintf('ST_Distance(Geography(e.%s), ST_GeomFromGeoJSON(:st_geojson)) AS metres', $repository->geometryField()))
            ->getQuery()
            ->getResult();

        self::assertSame(['near', 'far'], array_map(static fn (array $row): ?string => $row[0]->label, $rows));
        self::assertIsFloat($rows[0]['metres']);
        self::assertEqualsWithDelta(111.32, $rows[0]['metres'], 0.5);
    }

    public function testFindStWithinMetresMeasuresFromEveryPartOfACollection(): void
    {
        $this->persistThing(self::point(0.001, 0), 'beside the first');
        $this->persistThing(self::point(5.001, 5), 'beside the second');
        $this->persistThing(self::point(2.5, 2.5), 'between them');

        $collection = '{"type":"GeometryCollection","geometries":['
            .'{"type":"Point","coordinates":[0,0]},'
            .'{"type":"LineString","coordinates":[[5,5],[5,5.0001]]}]}';

        $labels = self::labels($this->repository()->findStWithinMetres($collection, 300.0));
        sort($labels);

        self::assertSame(['beside the first', 'beside the second'], $labels);
    }

    public function testFindStWithinMetresLeavesUnreadableGeoJsonToPostGis(): void
    {
        $this->expectException(DriverException::class);

        $this->repository()->findStWithinMetres('not GeoJSON', 300.0);
    }

    public function testFindStWithinMetresWorksOnAGeographyColumn(): void
    {
        foreach (['far' => self::point(0.002, 0), 'near' => self::point(0.001, 0)] as $label => $geoJson) {
            $place = new GeographyPlace();
            $place->position = $geoJson;
            $place->label = $label;
            $this->em->persist($place);
        }
        $this->em->flush();

        $found = (new GeographyPlaceRepository(new SingleManagerRegistry($this->em)))
            ->findStWithinMetres(self::point(0, 0), 300.0);

        self::assertSame(['near', 'far'], array_map(static fn (GeographyPlace $place): ?string => $place->label, $found));
    }

    public function testFindStWithinMetresRejectsANegativeRadiusAndANonPositiveLimit(): void
    {
        try {
            $this->repository()->findStWithinMetres(self::point(0, 0), -1.0);
            self::fail('A negative radius must be rejected.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('metres', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/limit/');

        $this->repository()->findStWithinMetres(self::point(0, 0), 10.0, [], 0);
    }

    public function testFindStWithinMetresRejectsAnUnknownCriteriaField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nope/');

        $this->repository()->findStWithinMetres(self::point(0, 0), 10.0, ['nope' => 1]);
    }
}
