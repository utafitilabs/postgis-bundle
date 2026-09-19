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
use PHPUnit\Framework\TestCase;
use UtafitiLabs\PostGISBundle\Types\GeographyType;

final class GeographyTypeTest extends TestCase
{
    private GeographyType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        if (!Type::hasType(GeographyType::NAME)) {
            Type::addType(GeographyType::NAME, GeographyType::class);
        }

        $type = Type::getType(GeographyType::NAME);
        self::assertInstanceOf(GeographyType::class, $type);

        $this->type = $type;
        $this->platform = new PostgreSQLPlatform();
    }

    public function testGetSqlDeclarationUsesGeographyKeyword(): void
    {
        self::assertSame(
            'geography(POINT,4326)',
            $this->type->getSQLDeclaration(['geometry_type' => 'Point', 'srid' => 4326], $this->platform),
        );
    }

    public function testWriteConversionCastsToGeography(): void
    {
        self::assertSame(
            'ST_GeomFromGeoJSON(?)::geography',
            $this->type->convertToDatabaseValueSQL('?', $this->platform),
        );
    }

    public function testReadConversionWrapsWithStAsGeoJson(): void
    {
        self::assertSame('ST_AsGeoJSON(t0.area)', $this->type->convertToPHPValueSQL('t0.area', $this->platform));
    }

    public function testMappedDatabaseTypes(): void
    {
        self::assertSame(['geography'], $this->type->getMappedDatabaseTypes($this->platform));
    }
}
