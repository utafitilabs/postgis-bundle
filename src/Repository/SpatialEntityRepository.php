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

namespace UtafitiLabs\PostGISBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use UtafitiLabs\PostGISBundle\Exception\MissingSpatialColumnException;
use UtafitiLabs\PostGISBundle\Types\GeometryType;

/**
 * Repository base with spatial superpowers. Extend it instead of
 * ServiceEntityRepository and the St methods just exist — no DQL to write:
 *
 *     final class AreaRepository extends SpatialEntityRepository { … }
 *
 *     $areas->findStIntersecting($geoJson);   // GiST-backed ST_Intersects
 *     $areas->stAreaKm2(['source' => 'x']);   // geodesic km²
 *
 * Every public method carries the St marker — the bundle's signature — so it can
 * never be confused with a Doctrine core method. The geometry column is
 * DISCOVERED from the entity's Doctrine metadata (the single source of truth);
 * an entity without one is rejected at construction with a teaching message.
 *
 * @template T of object
 *
 * @extends ServiceEntityRepository<T>
 */
class SpatialEntityRepository extends ServiceEntityRepository
{
    private readonly string $geometryField;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);

        $fields = $this->geometryFields();
        $this->geometryField = $fields[0] ?? throw MissingSpatialColumnException::forEntity($entityClass);
    }

    /**
     * Entities whose geometry intersects the given GeoJSON geometry —
     * ST_Intersects on the auto-created GiST index. Ordinary criteria narrow
     * the result further, findBy-style.
     *
     * @param array<string, mixed> $criteria
     *
     * @return list<T>
     */
    public function findStIntersecting(string $geoJson, array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where(\sprintf('ST_Intersects(e.%s, ST_GeomFromGeoJSON(:st_geojson)) = true', $this->geometryField))
            ->setParameter('st_geojson', $geoJson);
        $this->applyCriteria($qb, $criteria);

        /** @var list<T> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Geodesic area of the matching rows in km² — SUM(ST_Area(Geography(geom))),
     * measured on the spheroid, not in planar CRS units.
     *
     * @param array<string, mixed> $criteria
     */
    public function stAreaKm2(array $criteria = []): float
    {
        $qb = $this->createQueryBuilder('e')
            ->select(\sprintf('SUM(ST_Area(Geography(e.%s)))', $this->geometryField));
        $this->applyCriteria($qb, $criteria);

        $squareMetres = $qb->getQuery()->getSingleScalarResult();

        return is_numeric($squareMetres) ? (float) $squareMetres / 1e6 : 0.0;
    }

    /**
     * findBy-style criteria with a friendly error on a typo'd field.
     *
     * @param array<string, mixed> $criteria
     */
    private function applyCriteria(QueryBuilder $qb, array $criteria): void
    {
        $metadata = $this->getClassMetadata();
        foreach ($criteria as $field => $value) {
            if (!$metadata->hasField($field) && !$metadata->hasAssociation($field)) {
                throw new \InvalidArgumentException(\sprintf('Unknown criteria field "%s" on %s (known fields: %s).', $field, $this->getEntityName(), implode(', ', array_merge($metadata->getFieldNames(), $metadata->getAssociationNames()))));
            }
            $qb->andWhere(\sprintf('e.%s = :st_c_%s', $field, $field))
                ->setParameter('st_c_'.$field, $value);
        }
    }

    /**
     * Geometry/geography columns from the entity's own metadata — every spatial
     * type descends from GeometryType.
     *
     * @return list<string>
     */
    private function geometryFields(): array
    {
        $fields = [];
        foreach ($this->getClassMetadata()->getFieldNames() as $field) {
            $typeName = $this->getClassMetadata()->getTypeOfField($field);
            if (null === $typeName || !Type::getTypeRegistry()->has($typeName)) {
                continue;
            }
            if (Type::getTypeRegistry()->get($typeName) instanceof GeometryType) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
