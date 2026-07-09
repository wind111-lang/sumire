<?php

declare(strict_types=1);

namespace Sumire;

use InvalidArgumentException;
use Sumire\Exception\SumireException;
use Sumire\Mapping\EntityMetadata;
use Sumire\Mapping\MetadataFactory;
use Sumire\Mapping\PropertyMapping;

final class EntityManager
{
    private MetadataFactory $metadataFactory;

    public function __construct(
        private readonly Connection $connection,
        ?MetadataFactory $metadataFactory = null,
    ) {
        $this->metadataFactory = $metadataFactory ?? new MetadataFactory();
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return EntityRepository<T>
     */
    public function repository(string $entityClass): EntityRepository
    {
        return new EntityRepository($this, $entityClass);
    }

    /** @param class-string $entityClass */
    public function find(string $entityClass, mixed $id): ?object
    {
        $metadata = $this->metadataFactory->for($entityClass);
        $idMapping = $metadata->id();
        $params = ['id' => $id];
        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = :id LIMIT 1',
            $this->selectColumns($metadata),
            $this->connection->quoteIdentifier($metadata->tableName),
            $this->connection->quoteIdentifier($idMapping->columnName),
        );

        $row = $this->connection->fetchOne($sql, $params);

        return $row === null ? null : $metadata->hydrate($row);
    }

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return list<object>
     */
    public function findBy(string $entityClass, array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $metadata = $this->metadataFactory->for($entityClass);
        $params = [];
        $sql = sprintf(
            'SELECT %s FROM %s',
            $this->selectColumns($metadata),
            $this->connection->quoteIdentifier($metadata->tableName),
        );

        $where = $this->whereClause($metadata, $criteria, $params);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $order = $this->orderClause($metadata, $orderBy);
        if ($order !== '') {
            $sql .= ' ORDER BY ' . $order;
        }

        if ($limit !== null) {
            if ($limit < 0) {
                throw new InvalidArgumentException('Limit must be greater than or equal to zero.');
            }

            $sql .= ' LIMIT ' . $limit;
        }

        if ($offset !== null) {
            if ($offset < 0) {
                throw new InvalidArgumentException('Offset must be greater than or equal to zero.');
            }

            $sql .= ' OFFSET ' . $offset;
        }

        return array_map(
            static fn (array $row): object => $metadata->hydrate($row),
            $this->connection->fetchAll($sql, $params),
        );
    }

    public function persist(object $entity): void
    {
        $metadata = $this->metadataFactory->for($entity::class);
        $idMapping = $metadata->id();
        $id = $idMapping->getValue($entity);

        if ($idMapping->generated && $id === null) {
            $this->insert($entity);

            return;
        }

        if ($id === null) {
            $this->insert($entity);

            return;
        }

        $this->update($entity);
    }

    public function insert(object $entity): void
    {
        $metadata = $this->metadataFactory->for($entity::class);
        $properties = $metadata->insertableProperties($entity);
        $idMapping = $metadata->id();
        $params = [];
        $returnsGeneratedId = $idMapping->generated
            && $idMapping->getValue($entity) === null
            && $this->connection->driverName() === 'pgsql';

        if ($properties === []) {
            $sql = sprintf(
                'INSERT INTO %s DEFAULT VALUES',
                $this->connection->quoteIdentifier($metadata->tableName),
            );
        } else {
            $columns = [];
            $placeholders = [];

            foreach ($properties as $index => $mapping) {
                $param = 'p' . $index;
                $columns[] = $this->connection->quoteIdentifier($mapping->columnName);
                $placeholders[] = ':' . $param;
                $params[$param] = $mapping->getValue($entity);
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->connection->quoteIdentifier($metadata->tableName),
                implode(', ', $columns),
                implode(', ', $placeholders),
            );
        }

        if ($returnsGeneratedId) {
            $sql .= sprintf(' RETURNING %s', $this->connection->quoteIdentifier($idMapping->columnName));
            $row = $this->connection->fetchOne($sql, $params);

            if ($row !== null && array_key_exists($idMapping->columnName, $row)) {
                $idMapping->setValue($entity, $row[$idMapping->columnName]);
            }

            return;
        }

        $this->connection->execute($sql, $params);

        if ($idMapping->generated && $idMapping->getValue($entity) === null) {
            $idMapping->setValue($entity, $this->connection->lastInsertId());
        }
    }

    public function update(object $entity): void
    {
        $metadata = $this->metadataFactory->for($entity::class);
        $idMapping = $metadata->id();
        $id = $idMapping->getValue($entity);

        if ($id === null) {
            throw new SumireException(sprintf('Cannot update entity "%s" without an id value.', $entity::class));
        }

        $properties = $metadata->updatableProperties();
        if ($properties === []) {
            return;
        }

        $params = ['id' => $id];
        $assignments = [];

        foreach ($properties as $index => $mapping) {
            $param = 'p' . $index;
            $assignments[] = sprintf('%s = :%s', $this->connection->quoteIdentifier($mapping->columnName), $param);
            $params[$param] = $mapping->getValue($entity);
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->connection->quoteIdentifier($metadata->tableName),
            implode(', ', $assignments),
            $this->connection->quoteIdentifier($idMapping->columnName),
        );

        $this->connection->execute($sql, $params);
    }

    public function remove(object $entity): void
    {
        $metadata = $this->metadataFactory->for($entity::class);
        $idMapping = $metadata->id();
        $id = $idMapping->getValue($entity);

        if ($id === null) {
            throw new SumireException(sprintf('Cannot remove entity "%s" without an id value.', $entity::class));
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :id',
            $this->connection->quoteIdentifier($metadata->tableName),
            $this->connection->quoteIdentifier($idMapping->columnName),
        );

        $this->connection->execute($sql, ['id' => $id]);
    }

    public function transaction(callable $callback): mixed
    {
        return $this->connection->transaction(fn (): mixed => $callback($this));
    }

    private function selectColumns(EntityMetadata $metadata): string
    {
        return implode(', ', array_map(
            fn (PropertyMapping $mapping): string => $this->connection->quoteIdentifier($mapping->columnName),
            $metadata->properties(),
        ));
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $params
     */
    private function whereClause(EntityMetadata $metadata, array $criteria, array &$params): string
    {
        $parts = [];

        foreach ($criteria as $field => $value) {
            $mapping = $metadata->mappingForField((string) $field);
            $column = $this->connection->quoteIdentifier($mapping->columnName);

            if ($value === null) {
                $parts[] = $column . ' IS NULL';
                continue;
            }

            if (is_array($value)) {
                if ($value === []) {
                    $parts[] = '1 = 0';
                    continue;
                }

                $placeholders = [];
                foreach (array_values($value) as $index => $item) {
                    $param = 'w' . count($params) . '_' . $index;
                    $placeholders[] = ':' . $param;
                    $params[$param] = $item;
                }

                $parts[] = sprintf('%s IN (%s)', $column, implode(', ', $placeholders));
                continue;
            }

            $param = 'w' . count($params);
            $parts[] = sprintf('%s = :%s', $column, $param);
            $params[$param] = $value;
        }

        return implode(' AND ', $parts);
    }

    /** @param array<string, string> $orderBy */
    private function orderClause(EntityMetadata $metadata, array $orderBy): string
    {
        $parts = [];

        foreach ($orderBy as $field => $direction) {
            $normalized = strtoupper($direction);
            if (!in_array($normalized, ['ASC', 'DESC'], true)) {
                throw new InvalidArgumentException(sprintf('Invalid order direction "%s".', $direction));
            }

            $mapping = $metadata->mappingForField((string) $field);
            $parts[] = $this->connection->quoteIdentifier($mapping->columnName) . ' ' . $normalized;
        }

        return implode(', ', $parts);
    }
}
