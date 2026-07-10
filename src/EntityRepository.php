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
        private Database $database,
        private string $entityClass,
    ) {}

    /** @return T|null */
    public function find(mixed $id): ?object
    {
        return $this->database->find($this->entityClass, $id);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return list<T>
     */
    public function findBy(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->database->findBy($this->entityClass, $criteria, $orderBy, $limit, $offset);
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

    /** @param array<string, mixed> $criteria */
    public function count(array $criteria = []): int
    {
        return $this->database->count($this->entityClass, $criteria);
    }

    /** @param array<string, mixed> $criteria */
    public function exists(array $criteria = []): bool
    {
        return $this->database->exists($this->entityClass, $criteria);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return PaginatedResult<object>
     */
    public function paginate(array $criteria = [], array $orderBy = [], int $limit = 50, int $offset = 0): PaginatedResult
    {
        return $this->database->paginate($this->entityClass, $criteria, $orderBy, $limit, $offset);
    }

    /** @param T $entity */
    public function save(object $entity): void
    {
        $this->database->persist($entity);
    }

    /** @param T $entity */
    public function remove(object $entity): void
    {
        $this->database->remove($entity);
    }
}
