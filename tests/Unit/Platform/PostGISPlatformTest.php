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

namespace UtafitiLabs\PostGISBundle\Tests\Unit\Platform;

use Doctrine\DBAL\Schema\Index;
use PHPUnit\Framework\TestCase;
use UtafitiLabs\PostGISBundle\Platform\PostGISPlatform;

final class PostGISPlatformTest extends TestCase
{
    private PostGISPlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new PostGISPlatform();
    }

    public function testSpatialIndexIsCreatedUsingGist(): void
    {
        $index = new Index('idx_geom', ['geom'], false, false, ['spatial']);

        $sql = $this->platform->getCreateIndexSQL($index, 'spatial_thing');

        self::assertSame('CREATE INDEX idx_geom ON spatial_thing USING gist (geom)', $sql);
    }

    public function testRegularIndexKeepsDefaultAccessMethod(): void
    {
        $index = new Index('idx_name', ['name']);

        $sql = $this->platform->getCreateIndexSQL($index, 'spatial_thing');

        self::assertSame('CREATE INDEX idx_name ON spatial_thing (name)', $sql);
    }
}
