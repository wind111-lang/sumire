<?php

declare(strict_types=1);

namespace Sumire;

use PDO;
use PDOException;
use PDOStatement;
use Sumire\Exception\SumireException;

final class Connection
{
    private int $transactionDepth = 0;

    private int $savepointSequence = 0;

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
        if ($this->transactionDepth > 0 || $this->pdo->inTransaction()) {
            return $this->nestedTransaction($callback);
        }

        $this->pdo->beginTransaction();
        $this->transactionDepth = 1;

        try {
            $result = $callback($this);
            $this->guardActiveTransaction();
            $this->pdo->commit();

            return $result;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        } finally {
            $this->transactionDepth = 0;
        }
    }

    private function nestedTransaction(callable $callback): mixed
    {
        $this->guardActiveTransaction();

        $savepoint = $this->nextSavepointName();

        $this->executeSavepointSql(sprintf('SAVEPOINT %s', $savepoint));
        ++$this->transactionDepth;

        try {
            $result = $callback($this);
            $this->guardActiveTransaction();
            $this->executeSavepointSql(sprintf('RELEASE SAVEPOINT %s', $savepoint));

            return $result;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->executeSavepointSql(sprintf('ROLLBACK TO SAVEPOINT %s', $savepoint));
                $this->executeSavepointSql(sprintf('RELEASE SAVEPOINT %s', $savepoint));
            }

            throw $exception;
        } finally {
            --$this->transactionDepth;
        }
    }

    private function nextSavepointName(): string
    {
        return 'sumire_savepoint_' . ++$this->savepointSequence;
    }

    private function executeSavepointSql(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    private function guardActiveTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new SumireException('The transaction was closed before the callback returned.');
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
