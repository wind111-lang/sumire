# Examples

## Basic Entity

```php
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;

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

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
```

## SQLite Setup

```php
use Sumire\Database;

$database = Database::connect(new PDO('sqlite:' . __DIR__ . '/database.sqlite'));

$database->connection()->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    active INTEGER NOT NULL
)
SQL);
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

## Query for Null

```php
$posts = $database->findBy(Post::class, [
    'publishedAt' => null,
]);
```

This generates an `IS NULL` condition.

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

## Raw SQL

```php
$rows = $database->connection()->fetchAll(
    'SELECT email FROM users WHERE active = :active ORDER BY email ASC',
    ['active' => true],
);
```

Use raw SQL when you need a query that is outside Sumire's small repository API.
