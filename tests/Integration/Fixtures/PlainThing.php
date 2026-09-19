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

use Doctrine\ORM\Mapping as ORM;

/** An entity WITHOUT any geometry column — must be rejected by SpatialEntityRepository. */
#[ORM\Entity]
#[ORM\Table(name: 'plain_thing')]
class PlainThing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    public ?string $name = null;
}
