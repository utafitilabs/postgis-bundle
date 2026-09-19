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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

/**
 * Minimal ManagerRegistry over the standalone integration EntityManager, so
 * ServiceEntityRepository subclasses can be constructed without a kernel.
 */
final class SingleManagerRegistry implements ManagerRegistry
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): object
    {
        return $this->em->getConnection();
    }

    /** @return array<string, object> */
    public function getConnections(): array
    {
        return ['default' => $this->em->getConnection()];
    }

    /** @return array<string, string> */
    public function getConnectionNames(): array
    {
        return ['default' => 'default'];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->em;
    }

    /** @return array<string, ObjectManager> */
    public function getManagers(): array
    {
        return ['default' => $this->em];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        return $this->em;
    }

    /** @return array<string, string> */
    public function getManagerNames(): array
    {
        return ['default' => 'default'];
    }

    /**
     * @template TObject of object
     *
     * @param class-string<TObject> $persistentObject
     *
     * @return ObjectRepository<TObject>
     */
    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->em->getRepository($persistentObject);
    }

    public function getManagerForClass(string $class): ObjectManager
    {
        return $this->em;
    }
}
