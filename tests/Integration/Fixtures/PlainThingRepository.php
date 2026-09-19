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

namespace UtafitiLabs\PostGISBundle\Tests\Integration\Fixtures;

use Doctrine\Persistence\ManagerRegistry;
use UtafitiLabs\PostGISBundle\Repository\SpatialEntityRepository;

/**
 * Wrong by design: PlainThing has no geometry column, so constructing this
 * repository must throw.
 *
 * @extends SpatialEntityRepository<PlainThing>
 */
final class PlainThingRepository extends SpatialEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlainThing::class);
    }
}
