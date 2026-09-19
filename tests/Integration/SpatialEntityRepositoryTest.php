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

use UtafitiLabs\PostGISBundle\Exception\MissingSpatialColumnException;
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
}
