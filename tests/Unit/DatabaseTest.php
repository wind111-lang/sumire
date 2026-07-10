<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Sumire\Database;
use Sumire\Mapping\MetadataFactory;
use Sumire\Tests\Fixtures\User;

final class DatabaseTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        $this->database = Database::connect(new PDO('sqlite::memory:'));
        $connection = $this->database->connection();

        $connection->execute(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                active INTEGER NOT NULL
            )
            SQL);
    }

    public function testPersistsFindsUpdatesAndRemovesEntity(): void
    {
        $user = new User('Ada Lovelace', 'ada@example.com');
        $this->database->persist($user);

        self::assertSame(1, $user->id());

        $found = $this->database->find(User::class, $user->id());

        self::assertInstanceOf(User::class, $found);
        self::assertSame('Ada Lovelace', $found->name());
        self::assertTrue($found->active());

        $found->rename('Ada King');
        $found->changeEmail('ada.king@example.com');
        $found->deactivate();
        $this->database->persist($found);

        $repository = $this->database->repository(User::class);
        $inactive = $repository->firstBy(['active' => false]);

        self::assertInstanceOf(User::class, $inactive);
        self::assertSame('ada.king@example.com', $inactive->email());

        $this->database->persist(new User('Grace Hopper', 'grace@example.com'));
        $ordered = $repository->findBy([], ['name' => 'DESC'], 1);

        self::assertCount(1, $ordered);
        self::assertSame('Grace Hopper', $ordered[0]->name());

        $this->database->remove($inactive);

        self::assertNull($repository->find($inactive->id()));
    }

    public function testHydratesPostgresBooleanStrings(): void
    {
        $metadata = (new MetadataFactory())->for(User::class);
        $postgresFalse = $metadata->hydrate([
            'id' => '10',
            'name' => 'Postgres False',
            'email' => 'postgres.false@example.com',
            'active' => 'f',
        ]);
        $postgresTrue = $metadata->hydrate([
            'id' => '11',
            'name' => 'Postgres True',
            'email' => 'postgres.true@example.com',
            'active' => 't',
        ]);

        self::assertInstanceOf(User::class, $postgresFalse);
        self::assertFalse($postgresFalse->active());
        self::assertInstanceOf(User::class, $postgresTrue);
        self::assertTrue($postgresTrue->active());
    }

    public function testRollsBackFailedTransaction(): void
    {
        $repository = $this->database->repository(User::class);

        try {
            $this->database->transaction(function (Database $transactional): void {
                $transactional->persist(new User('Rollback Test', 'rollback@example.com'));

                throw new PDOException('rollback');
            });
        } catch (PDOException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertNull($repository->firstBy(['email' => 'rollback@example.com']));
    }

    public function testNestedTransactionRollsBackOnlyFailedSavepoint(): void
    {
        $repository = $this->database->repository(User::class);

        $this->database->transaction(function (Database $outer) use ($repository): void {
            $outer->persist(new User('Outer Commit', 'outer@example.com'));

            try {
                $outer->transaction(function (Database $inner): void {
                    $inner->persist(new User('Inner Rollback', 'inner.rollback@example.com'));

                    throw new PDOException('inner rollback');
                });
            } catch (PDOException $exception) {
                self::assertSame('inner rollback', $exception->getMessage());
            }

            self::assertNull($repository->firstBy(['email' => 'inner.rollback@example.com']));

            $outer->persist(new User('After Inner Rollback', 'after@example.com'));
        });

        self::assertInstanceOf(User::class, $repository->firstBy(['email' => 'outer@example.com']));
        self::assertInstanceOf(User::class, $repository->firstBy(['email' => 'after@example.com']));
        self::assertNull($repository->firstBy(['email' => 'inner.rollback@example.com']));
    }

    public function testOuterRollbackRollsBackSuccessfulNestedTransaction(): void
    {
        $repository = $this->database->repository(User::class);

        try {
            $this->database->transaction(function (Database $outer): void {
                $outer->persist(new User('Outer Rollback', 'outer.rollback@example.com'));

                $outer->transaction(function (Database $inner): void {
                    $inner->persist(new User('Inner Released', 'inner.released@example.com'));
                });

                throw new PDOException('outer rollback');
            });
        } catch (PDOException $exception) {
            self::assertSame('outer rollback', $exception->getMessage());
        }

        self::assertNull($repository->firstBy(['email' => 'outer.rollback@example.com']));
        self::assertNull($repository->firstBy(['email' => 'inner.released@example.com']));
    }

    public function testTransactionReturnsCallbackResult(): void
    {
        $result = $this->database->transaction(
            static fn(Database $database): string => $database->transaction(
                static fn(Database $_database): string => 'nested-result',
            ),
        );

        self::assertSame('nested-result', $result);
    }
}
