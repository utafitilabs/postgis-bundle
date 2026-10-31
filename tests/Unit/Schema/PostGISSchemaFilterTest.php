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

namespace UtafitiLabs\PostGISBundle\Tests\Unit\Schema;

use Doctrine\Bundle\DoctrineBundle\Dbal\RegexSchemaAssetFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle;

/**
 * The schema filter the bundle prepends, applied the way DoctrineBundle
 * applies doctrine.dbal.schema_filter: an asset is kept when the expression matches.
 */
final class PostGISSchemaFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function assets(): iterable
    {
        yield 'spatial_ref_sys' => ['spatial_ref_sys', false];
        yield 'spatial_ref_sys in public' => ['public.spatial_ref_sys', false];
        yield 'spatial_ref_sys in another schema' => ['postgis.spatial_ref_sys', false];
        yield 'the topology table' => ['topology.topology', false];
        yield 'the topology layer table' => ['topology.layer', false];
        yield 'the topology sequence' => ['topology.topology_id_seq', false];
        yield 'an application table' => ['venue', true];
        yield 'an application table named layer' => ['layer', true];
        yield 'an application table named topology' => ['topology', true];
        yield 'a table whose name contains spatial_ref_sys' => ['my_spatial_ref_sys', true];
        yield 'an application sequence' => ['venue_id_seq', true];
        yield 'an application table in its own schema' => ['catalogue.venue', true];
    }

    #[DataProvider('assets')]
    public function testTheFilterKeepsApplicationAssetsAndDropsPostGISOwn(string $asset, bool $kept): void
    {
        $filter = new RegexSchemaAssetFilter(UtafitiLabsPostGISBundle::POSTGIS_SCHEMA_FILTER);

        self::assertSame($kept, $filter($asset));
    }

    public function testTheDocumentedCombinedExpressionKeepsBothRules(): void
    {
        $filter = new RegexSchemaAssetFilter('~^(?!legacy_|(?:[^.]+\\.)?spatial_ref_sys$|topology\\.)~');

        self::assertFalse($filter('legacy_orders'));
        self::assertFalse($filter('spatial_ref_sys'));
        self::assertFalse($filter('topology.topology_id_seq'));
        self::assertTrue($filter('venue'));
    }
}
