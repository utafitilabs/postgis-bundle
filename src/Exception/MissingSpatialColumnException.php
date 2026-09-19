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

namespace UtafitiLabs\PostGISBundle\Exception;

/**
 * A repository extends SpatialEntityRepository for an entity that has no
 * geometry/geography column — the entity's Doctrine metadata is the single
 * source of truth, so the mismatch is caught at construction, with a teaching
 * message.
 */
final class MissingSpatialColumnException extends \LogicException
{
    /**
     * @param class-string $entityClass
     */
    public static function forEntity(string $entityClass): self
    {
        return new self(\sprintf(
            '%s has no geometry/geography column. SpatialEntityRepository requires one — declare a spatial column (e.g. #[ORM\Column(type: \'geometry\')] or a typed sub-type like \'multipolygon\'), or extend ServiceEntityRepository instead.',
            $entityClass,
        ));
    }
}
