<?php

declare(strict_types=1);

namespace Sumire;

use PDO;
use PDOStatement;
use Throwable;

final class Connection
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->rowCount();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->fetchAll();
    }

    public function driverName(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function quoteIdentifier(string $identifier): string
    {
        [$open, $close] = match ($this->driverName()) {
            'mysql' => ['`', '`'],
            'sqlsrv' => ['[', ']'],
            default => ['"', '"'],
        };

        $escape = $close === ']' ? ']]' : $close . $close;

        return implode('.', array_map(
            static fn(string $part): string => $open . str_replace($close, $escape, $part) . $close,
            explode('.', $identifier),
        ));
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();

            throw $throwable;
        }
    }

    /** @param array<string, mixed> $params */
    private function prepareAndExecute(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . ltrim((string) $name, ':'), $value, $this->paramType($value));
        }

        $statement->execute();

        return $statement;
    }

    private function paramType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
    }
}
