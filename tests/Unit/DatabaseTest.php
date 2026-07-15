<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Sumire\Database;
use Sumire\Mapping\MetadataFactory;
use Sumire\Tests\Fixtures\Post;
use Sumire\Tests\Fixtures\PostStatus;
use Sumire\Tests\Fixtures\User;

final class DatabaseTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        $this->database = Database::connect(new PDO('sqlite::memory:'));
        $this->database->createTable(User::class);
        $this->database->createTable(Post::class);
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

        $createdAt = new DateTimeImmutable('2026-07-09 12:34:56');
        $this->database->persist(new Post('Typed Criteria', PostStatus::Draft, [], $createdAt));

        $typedPosts = $this->database->repository(Post::class)->findBy([
            'status IN' => [PostStatus::Draft],
            'createdAt >=' => $createdAt,
        ]);

        self::assertCount(1, $typedPosts);
        self::assertSame('Typed Criteria', $typedPosts[0]->title());
    }

    public function testInsertReturnsIdAndUpdateReturnsAffectedRows(): void
    {
        $user = new User('Ada Lovelace', 'ada@example.com');

        $insertedId = $this->database->insert($user);

        self::assertSame(1, $insertedId);
        self::assertSame(1, $user->id());

        $user->rename('Ada King');

        self::assertSame(1, $this->database->update($user));

        $found = $this->database->find(User::class, $user->id());

        self::assertInstanceOf(User::class, $found);
        self::assertSame('Ada King', $found->name());
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

    public function testCountsAndChecksExistence(): void
    {
        $this->database->persist(new User('Ada Lovelace', 'ada@example.com'));
        $this->database->persist(new User('Grace Hopper', 'grace@example.com', false));

        $repository = $this->database->repository(User::class);

        self::assertSame(2, $repository->count());
        self::assertSame(1, $repository->count(['active' => false]));
        self::assertSame(1, $repository->count(['id >' => 1]));
        self::assertTrue($repository->exists(['email' => 'ada@example.com']));
        self::assertTrue($repository->exists(['email LIKE' => '%@example.com']));
        self::assertFalse($repository->exists(['email' => 'missing@example.com']));

        self::assertSame(1, $this->database->count(User::class, ['email' => ['ada@example.com', 'missing@example.com']]));
        self::assertTrue($this->database->exists(User::class));

        $createdAt = new DateTimeImmutable('2026-07-09 12:34:56');
        $this->database->persist(new Post('Typed Criteria', PostStatus::Draft, [], $createdAt));

        $posts = $this->database->repository(Post::class);

        self::assertSame(1, $posts->count(['status' => PostStatus::Draft]));
        self::assertTrue($posts->exists(['createdAt' => $createdAt]));
    }

    public function testPaginatesRepositoryResults(): void
    {
        $this->database->persist(new User('Ada Lovelace', 'ada@example.com'));
        $this->database->persist(new User('Grace Hopper', 'grace@example.com'));
        $this->database->persist(new User('Katherine Johnson', 'katherine@example.com'));

        $page = $this->database->repository(User::class)->paginate(
            orderBy: ['id' => 'ASC'],
            limit: 2,
            offset: 1,
        );

        self::assertSame(3, $page->total);
        self::assertSame(2, $page->limit);
        self::assertSame(1, $page->offset);
        self::assertTrue($page->hasPreviousPage());
        self::assertFalse($page->hasNextPage());
        self::assertCount(2, $page->items);
        self::assertSame('Grace Hopper', $page->items[0]->name());
        self::assertSame('Katherine Johnson', $page->items[1]->name());

        $activePage = $this->database->paginate(User::class, ['active' => true], ['id' => 'ASC'], 1);

        self::assertSame(3, $activePage->total);
        self::assertTrue($activePage->hasNextPage());
        self::assertFalse($activePage->hasPreviousPage());
        self::assertCount(1, $activePage->items);
    }

    public function testPersistsAndHydratesTypedColumns(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-09 12:34:56');
        $post = new Post(
            'Typed Columns',
            PostStatus::Draft,
            ['tags' => ['sumire', 'php'], 'views' => 10],
            $createdAt,
        );

        $this->database->persist($post);

        self::assertSame(1, $post->id());

        $found = $this->database->repository(Post::class)->firstBy([
            'status' => PostStatus::Draft,
            'createdAt' => $createdAt,
        ]);

        self::assertInstanceOf(Post::class, $found);
        self::assertSame('Typed Columns', $found->title());
        self::assertSame(PostStatus::Draft, $found->status());
        self::assertSame(['tags' => ['sumire', 'php'], 'views' => 10], $found->metadata());
        self::assertSame('2026-07-09 12:34:56', $found->createdAt()->format('Y-m-d H:i:s'));

        $found->revise(PostStatus::Published, ['tags' => ['release'], 'views' => 20]);
        $this->database->persist($found);

        $published = $this->database->find(Post::class, $post->id());

        self::assertInstanceOf(Post::class, $published);
        self::assertSame(PostStatus::Published, $published->status());
        self::assertSame(['tags' => ['release'], 'views' => 20], $published->metadata());
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
