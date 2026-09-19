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

namespace UtafitiLabs\PostGISBundle\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\Types\Type;

/**
 * Makes schema introspection typmod-aware. PostGIS stores every column as the
 * base type `geometry`, so Doctrine's default introspection collapses a
 * `geometry(MultiPolygon,4326)` column to the generic type — and then every
 * `migrations:diff` wants to "fix" it (churn). Postgres already reports the full
 * typmod as `complete_type` (e.g. `geometry(MultiPolygon,4326)`); this reads the
 * kind back and swaps in the matching typed sub-type, so the introspected column
 * equals the mapped one and diffs stay clean.
 */
final class PostGISSchemaManager extends PostgreSQLSchemaManager
{
    /**
     * @param array<string, mixed> $tableColumn
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $column = parent::_getPortableTableColumnDefinition($tableColumn);

        $rawType = $tableColumn['type'] ?? null;
        $dbType = \is_string($rawType) ? strtolower($rawType) : '';
        if ('geometry' !== $dbType && 'geography' !== $dbType) {
            return $column;
        }

        // e.g. "geometry(MultiPolygon,4326)" — the kind inside the typmod is what
        // distinguishes a MultiPolygon column from a generic one.
        $completeType = $tableColumn['complete_type'] ?? null;
        if (!\is_string($completeType)) {
            return $column;
        }

        if (1 === preg_match('/^(?:geometry|geography)\(\s*([A-Za-z]+?)(?:[ZM]{1,2})?\s*,\s*\d+\s*\)$/', $completeType, $m)) {
            $kind = strtolower($m[1]); // multipolygon, point, geometry, ...
            if (Type::hasType($kind)) {
                $column->setType(Type::getType($kind));
            }
        }

        return $column;
    }
}
