<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sumire\Connection;
use Sumire\EntityManager;
use Sumire\Mapping\MetadataFactory;
use Sumire\Tests\Fixtures\User;

final class EntityManagerTest extends TestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $connection = new Connection(new PDO('sqlite::memory:'));
        $this->entityManager = new EntityManager($connection);

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
        $this->entityManager->persist($user);

        self::assertSame(1, $user->id());

        $found = $this->entityManager->find(User::class, $user->id());

        self::assertInstanceOf(User::class, $found);
        self::assertSame('Ada Lovelace', $found->name());
        self::assertTrue($found->active());

        $found->rename('Ada King');
        $found->changeEmail('ada.king@example.com');
        $found->deactivate();
        $this->entityManager->persist($found);

        $repository = $this->entityManager->repository(User::class);
        $inactive = $repository->firstBy(['active' => false]);

        self::assertInstanceOf(User::class, $inactive);
        self::assertSame('ada.king@example.com', $inactive->email());

        $this->entityManager->persist(new User('Grace Hopper', 'grace@example.com'));
        $ordered = $repository->findBy([], ['name' => 'DESC'], 1);

        self::assertCount(1, $ordered);
        self::assertSame('Grace Hopper', $ordered[0]->name());

        $this->entityManager->remove($inactive);

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
        $repository = $this->entityManager->repository(User::class);

        try {
            $this->entityManager->transaction(function (EntityManager $transactional): void {
                $transactional->persist(new User('Rollback Test', 'rollback@example.com'));

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertNull($repository->firstBy(['email' => 'rollback@example.com']));
    }
}
