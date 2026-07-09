<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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

    public function testFindBySupportsCriteriaOperators(): void
    {
        $this->database->persist(new User('Ada Lovelace', 'ada@example.com'));
        $this->database->persist(new User('Grace Hopper', 'grace@example.com', false));
        $this->database->persist(new User('Katherine Johnson', 'katherine@example.com'));

        $repository = $this->database->repository(User::class);

        $activeUsers = $repository->findBy(['id >' => 1, 'active !=' => false], ['id' => 'ASC']);

        self::assertCount(1, $activeUsers);
        self::assertSame('Katherine Johnson', $activeUsers[0]->name());

        $matchingNames = $repository->findBy(['name LIKE' => '%e%'], ['id' => 'ASC']);

        self::assertCount(3, $matchingNames);

        $notAda = $repository->findBy(['email NOT IN' => ['ada@example.com']], ['id' => 'ASC']);

        self::assertCount(2, $notAda);
        self::assertSame('Grace Hopper', $notAda[0]->name());

        $between = $repository->findBy(['id BETWEEN' => [1, 2]], ['id' => 'ASC']);

        self::assertCount(2, $between);
        self::assertSame('Ada Lovelace', $between[0]->name());
        self::assertSame('Grace Hopper', $between[1]->name());

        self::assertCount(0, $repository->findBy(['id IN' => []]));
        self::assertCount(3, $repository->findBy(['id NOT IN' => []]));
        self::assertCount(3, $repository->findBy(['id IS NOT NULL' => true]));
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

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertNull($repository->firstBy(['email' => 'rollback@example.com']));
    }
}
