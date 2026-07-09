<?php

declare(strict_types=1);

namespace Sumire;

/**
 * @template T of object
 */
final readonly class EntityRepository
{
    /** @param class-string<T> $entityClass */
    public function __construct(
        private EntityManager $entityManager,
        private string $entityClass,
    ) {}

    /** @return T|null */
    public function find(mixed $id): ?object
    {
        return $this->entityManager->find($this->entityClass, $id);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return list<T>
     */
    public function findBy(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->entityManager->findBy($this->entityClass, $criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return T|null
     */
    public function firstBy(array $criteria = [], array $orderBy = []): ?object
    {
        return $this->findBy($criteria, $orderBy, 1)[0] ?? null;
    }

    /** @return list<T> */
    public function all(): array
    {
        return $this->findBy();
    }

    /** @param T $entity */
    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    /** @param T $entity */
    public function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }
}
