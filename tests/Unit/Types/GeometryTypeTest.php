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

namespace UtafitiLabs\PostGISBundle\Tests\Unit\Types;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UtafitiLabs\PostGISBundle\Types\GeometryType;

final class GeometryTypeTest extends TestCase
{
    private GeometryType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        if (!Type::hasType(GeometryType::NAME)) {
            Type::addType(GeometryType::NAME, GeometryType::class);
        }

        $type = Type::getType(GeometryType::NAME);
        self::assertInstanceOf(GeometryType::class, $type);

        $this->type = $type;
        $this->platform = new PostgreSQLPlatform();
    }

    /**
     * @param array<string, mixed> $column
     */
    #[DataProvider('declarationProvider')]
    public function testGetSqlDeclaration(array $column, string $expected): void
    {
        self::assertSame($expected, $this->type->getSQLDeclaration($column, $this->platform));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function declarationProvider(): iterable
    {
        yield 'typed with srid' => [['geometry_type' => 'MultiPolygon', 'srid' => 4326], 'geometry(MULTIPOLYGON,4326)'];
        yield 'defaults to GEOMETRY 4326' => [[], 'geometry(GEOMETRY,4326)'];
        yield 'untyped srid 0 collapses to bare geometry' => [['srid' => 0], 'geometry'];
    }

    public function testReadConversionWrapsWithStAsGeoJson(): void
    {
        self::assertSame('ST_AsGeoJSON(t0.geom)', $this->type->convertToPHPValueSQL('t0.geom', $this->platform));
    }

    public function testWriteConversionWrapsWithStGeomFromGeoJson(): void
    {
        self::assertSame('ST_GeomFromGeoJSON(?)', $this->type->convertToDatabaseValueSQL('?', $this->platform));
    }

    public function testConvertToDatabaseValueNormalisesToGeoJsonText(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertSame('{"type":"Point"}', $this->type->convertToDatabaseValue(['type' => 'Point'], $this->platform));
        self::assertSame('{"type":"Point"}', $this->type->convertToDatabaseValue('{"type":"Point"}', $this->platform));
    }

    public function testConvertToPhpValuePassesThroughGeoJson(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertSame('{"type":"Point"}', $this->type->convertToPHPValue('{"type":"Point"}', $this->platform));
    }

    public function testConvertToPhpValueCastsScalarsAndRejectsNonScalars(): void
    {
        self::assertSame('42', $this->type->convertToPHPValue(42, $this->platform));
        self::assertNull($this->type->convertToPHPValue(new \stdClass(), $this->platform));
    }

    public function testMappedDatabaseTypes(): void
    {
        self::assertSame(['geometry'], $this->type->getMappedDatabaseTypes($this->platform));
    }
}
