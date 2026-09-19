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

namespace UtafitiLabs\PostGISBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use UtafitiLabs\PostGISBundle\EventListener\SpatialSchemaListener;
use UtafitiLabs\PostGISBundle\ORM\Functions\Geography;
use UtafitiLabs\PostGISBundle\ORM\Functions\StArea;
use UtafitiLabs\PostGISBundle\ORM\Functions\StAsGeoJson;
use UtafitiLabs\PostGISBundle\ORM\Functions\StCollectionExtract;
use UtafitiLabs\PostGISBundle\ORM\Functions\StDWithin;
use UtafitiLabs\PostGISBundle\ORM\Functions\StGeomFromGeoJson;
use UtafitiLabs\PostGISBundle\ORM\Functions\StIntersects;
use UtafitiLabs\PostGISBundle\ORM\Functions\StMakeValid;
use UtafitiLabs\PostGISBundle\ORM\Functions\StMulti;
use UtafitiLabs\PostGISBundle\ORM\Functions\StSimplifyPreserveTopology;
use UtafitiLabs\PostGISBundle\ORM\Functions\StUnion;
use UtafitiLabs\PostGISBundle\Platform\PostGISMiddleware;
use UtafitiLabs\PostGISBundle\Schema\PostGISSchemaManagerFactory;
use UtafitiLabs\PostGISBundle\Types\GeographyType;
use UtafitiLabs\PostGISBundle\Types\GeometryType;
use UtafitiLabs\PostGISBundle\Types\LineStringType;
use UtafitiLabs\PostGISBundle\Types\MultiPolygonType;
use UtafitiLabs\PostGISBundle\Types\PointType;
use UtafitiLabs\PostGISBundle\Types\PolygonType;

/**
 * Enables PostGIS for a Symfony/Doctrine app with zero manual wiring: spatial types
 * (generic + typed sub-types), `ST_*` DQL functions, the `USING gist` platform
 * middleware, the typmod-aware schema manager (clean diffs), and the auto-GiST
 * schema listener. Just add the bundle.
 */
final class UtafitiLabsPostGISBundle extends AbstractBundle
{
    protected string $extensionAlias = 'utafiti_labs_post_gis';

    // Leading dot: internal services, hidden from `debug:container` (Symfony
    // bundle best practice for services not meant to be used by the app).
    private const SCHEMA_MANAGER_FACTORY_ID = '.utafiti_labs_post_gis.schema_manager_factory';

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'types' => [
                    GeometryType::NAME => GeometryType::class,
                    GeographyType::NAME => GeographyType::class,
                    LineStringType::NAME => LineStringType::class,
                    MultiPolygonType::NAME => MultiPolygonType::class,
                    PointType::NAME => PointType::class,
                    PolygonType::NAME => PolygonType::class,
                ],
                // Map the base PostGIS DB types back to a Doctrine type for
                // introspection; the schema manager then refines to the sub-type.
                'mapping_types' => [
                    'geometry' => GeometryType::NAME,
                    'geography' => GeographyType::NAME,
                ],
                'schema_manager_factory' => self::SCHEMA_MANAGER_FACTORY_ID,
            ],
            'orm' => [
                'dql' => [
                    'string_functions' => [
                        'Geography' => Geography::class,
                        'ST_AsGeoJSON' => StAsGeoJson::class,
                        'ST_CollectionExtract' => StCollectionExtract::class,
                        'ST_GeomFromGeoJSON' => StGeomFromGeoJson::class,
                        'ST_MakeValid' => StMakeValid::class,
                        'ST_Multi' => StMulti::class,
                        'ST_SimplifyPreserveTopology' => StSimplifyPreserveTopology::class,
                        'ST_Union' => StUnion::class,
                    ],
                    'numeric_functions' => [
                        'ST_Area' => StArea::class,
                        'ST_DWithin' => StDWithin::class,
                        'ST_Intersects' => StIntersects::class,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(self::SCHEMA_MANAGER_FACTORY_ID, PostGISSchemaManagerFactory::class);

        $services->set('.utafiti_labs_post_gis.middleware', PostGISMiddleware::class)
            ->tag('doctrine.middleware');

        $services->set('.utafiti_labs_post_gis.schema_listener', SpatialSchemaListener::class)
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
    }
}
