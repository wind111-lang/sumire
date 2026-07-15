# Examples

## Basic Entity

```php
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\Mapping\DomainMapper;

#[Table('users')]
final class User
{
    #[Id]
    private ?int $id = null;

    #[Column]
    private string $name;

    #[Column]
    private string $email;

    #[Column]
    private bool $active = true;

    public function __construct(string $name, string $email, bool $active = true)
    {
        $this->name = $name;
        $this->email = $email;
        $this->active = $active;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
```

## Domain Model Conversion

```php
final readonly class DomainUser
{
    public function __construct(
        public string $name,
        public string $email,
        public bool $active,
    ) {}
}

$domainUser = new DomainUser('Ada Lovelace', 'ada@example.com', true);

$mapper = DomainMapper::between(
    entityClass: User::class,
    domainClass: DomainUser::class,
    fromDomain: static fn(DomainUser $user): User => new User($user->name, $user->email, $user->active),
    toDomain: static fn(User $user): DomainUser => new DomainUser($user->name(), $user->email(), $user->active()),
);

$entity = $mapper->fromDomain($domainUser);
$mappedDomainUser = $mapper->toDomain($entity);
```

The domain model does not need Sumire attributes. `DomainMapper` validates both input types and both mapper return types.

## SQLite Setup

```php
use Sumire\Database;

$database = Database::connect(new PDO('sqlite:' . __DIR__ . '/database.sqlite'));
$database->createTable(User::class, ifNotExists: true);
```

## Insert and Read

```php
$user = new User('Ada Lovelace', 'ada@example.com');

$id = $database->insert($user);

echo $id;

$sameUser = $database->find(User::class, $user->id());
```

## Query by Criteria

```php
$users = $database->repository(User::class);

$activeUsers = $users->findBy(
    criteria: ['active' => true],
    orderBy: ['name' => 'ASC'],
    limit: 10,
);
```

## Paginate Results

```php
$page = $database->repository(User::class)->paginate(
    criteria: ['active' => true],
    orderBy: ['name' => 'ASC'],
    limit: 20,
    offset: 40,
);

foreach ($page->items as $user) {
    // Render the current page.
}

$hasMore = $page->hasNextPage();
```

## Query with `IN`

```php
$users = $database->repository(User::class)->findBy([
    'email' => [
        'ada@example.com',
        'grace@example.com',
    ],
]);
```

## Query with Operators

```php
$users = $database->repository(User::class)->findBy([
    'id >' => 100,
    'name LIKE' => 'Ada%',
    'email NOT IN' => [
        'blocked@example.com',
    ],
]);
```

`BETWEEN` accepts exactly two values.

```php
$users = $database->repository(User::class)->findBy([
    'id BETWEEN' => [100, 200],
]);
```

## Query for Null

```php
$posts = $database->findBy(Post::class, [
    'publishedAt' => null,
]);
```

This generates an `IS NULL` condition.

Use `!= null` or `IS NOT NULL` when you need the opposite condition.

```php
$posts = $database->findBy(Post::class, [
    'publishedAt !=' => null,
]);
```

## Count and Exists

```php
$users = $database->repository(User::class);

$activeCount = $users->count(['active' => true]);
$emailTaken = $users->exists(['email' => 'ada@example.com']);
```

## Typed Columns

```php
use DateTimeImmutable;
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\ColumnType;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

#[Table('posts')]
final class Post
{
    #[Id]
    private ?int $id = null;

    #[Column]
    private string $title;

    #[Column]
    private PostStatus $status;

    /** @var array<string, mixed> */
    #[Column(type: ColumnType::Json)]
    private array $metadata;

    #[Column]
    private DateTimeImmutable $createdAt;
}
```

Backed enums and DateTime values can be used directly in criteria.

```php
$posts = $database->repository(Post::class)->findBy([
    'status' => PostStatus::Published,
    'createdAt' => new DateTimeImmutable('2026-07-09 12:00:00'),
]);
```

## Update

```php
$user = $database->repository(User::class)->find(1);
$user->deactivate();

$affectedRows = $database->update($user);
```

`update()` returns the affected row count.

## Delete

```php
$user = $database->repository(User::class)->find(1);

$database->remove($user);
```

## Transaction

```php
$database->transaction(function (Database $database): void {
    $database->persist(new User('Ada Lovelace', 'ada@example.com'));
    $database->persist(new User('Grace Hopper', 'grace@example.com'));
});
```

If any statement fails, the transaction is rolled back.

## Nested Transaction with Savepoint

```php
$database->transaction(function (Database $database): void {
    $database->persist(new User('Outer Commit', 'outer@example.com'));

    try {
        $database->transaction(function (Database $database): void {
            $database->persist(new User('Inner Rollback', 'inner@example.com'));

            throw new RuntimeException('rollback inner work only');
        });
    } catch (RuntimeException) {
        // Only the inner transaction was rolled back.
    }

    $database->persist(new User('After Inner Rollback', 'after@example.com'));
});
```

Nested transactions use database savepoints. A successful inner transaction releases its savepoint, and a failed inner transaction rolls back to its savepoint. The outer transaction remains responsible for the final commit.

## Raw SQL

```php
$rows = $database->connection()->fetchAll(
    'SELECT email FROM users WHERE active = :active ORDER BY email ASC',
    ['active' => true],
);
```

Use raw SQL when you need a query that is outside Sumire's small repository API.
