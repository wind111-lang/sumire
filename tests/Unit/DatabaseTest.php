<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
        $connection = $this->database->connection();

        $connection->execute(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                active INTEGER NOT NULL
            )
            SQL);

        $connection->execute(<<<'SQL'
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                status TEXT NOT NULL,
                metadata TEXT NOT NULL,
                created_at TEXT NOT NULL
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
        self::assertTrue($repository->exists(['email' => 'ada@example.com']));
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

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }

        self::assertNull($repository->firstBy(['email' => 'rollback@example.com']));
    }
}
