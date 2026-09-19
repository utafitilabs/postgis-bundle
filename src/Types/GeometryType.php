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

namespace UtafitiLabs\PostGISBundle\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * PostGIS `geometry` column, exchanged with PHP as GeoJSON text.
 *
 * Read/write conversion happens inside PostgreSQL via ST_AsGeoJSON / ST_GeomFromGeoJSON,
 * so the application never hand-writes spatial SQL: declare the column and use it.
 *
 *   #[ORM\Column(type: 'geometry', options: ['geometry_type' => 'MultiPolygon', 'srid' => 4326])]
 *   public ?string $geom = null;  // GeoJSON string in, GeoJSON string out
 *
 * GeoJSON is defined in WGS84 (RFC 7946), hence the 4326 default.
 */
class GeometryType extends Type
{
    public const string NAME = 'geometry';

    /** PostGIS base column keyword — overridden by GeographyType. */
    protected function columnType(): string
    {
        return 'geometry';
    }

    /** Default geometry kind for the declaration; typed sub-types override this. */
    protected function defaultGeometryType(): string
    {
        return 'GEOMETRY';
    }

    /** SQL used to turn a stored value into the PHP (GeoJSON) representation on read. */
    protected function toGeoJsonSql(string $sqlExpr): string
    {
        return \sprintf('ST_AsGeoJSON(%s)', $sqlExpr);
    }

    /** SQL used to turn the PHP (GeoJSON) value into a stored value on write. */
    protected function fromGeoJsonSql(string $sqlExpr): string
    {
        return \sprintf('ST_GeomFromGeoJSON(%s)', $sqlExpr);
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $rawType = $column['geometry_type'] ?? $this->defaultGeometryType();
        $geometryType = strtoupper(\is_string($rawType) ? $rawType : $this->defaultGeometryType());

        $rawSrid = $column['srid'] ?? 4326;
        $srid = is_numeric($rawSrid) ? (int) $rawSrid : 4326;

        if ('GEOMETRY' === $geometryType && 0 === $srid) {
            return $this->columnType();
        }

        return \sprintf('%s(%s,%d)', $this->columnType(), $geometryType, $srid);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $this->toGeoJsonSql($sqlExpr);
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $this->fromGeoJsonSql($sqlExpr);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        // Already GeoJSON text produced by ST_AsGeoJSON.
        if (null === $value || \is_string($value)) {
            return $value;
        }

        return \is_scalar($value) ? (string) $value : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        // Accept either a GeoJSON string or an array/JsonSerializable, always store GeoJSON text.
        if (\is_string($value)) {
            return $value;
        }

        return json_encode($value, \JSON_THROW_ON_ERROR);
    }

    /** Map the PostGIS DB type back to this Doctrine type during schema introspection. */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [$this->columnType()];
    }
}
