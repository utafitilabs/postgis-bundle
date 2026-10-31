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

/** An entity whose spatial column is geography, not geometry. */
#[ORM\Entity]
#[ORM\Table(name: 'geography_place')]
class GeographyPlace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'geography')]
    public ?string $position = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    public ?string $label = null;
}
