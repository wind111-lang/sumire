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

$database->persist($user);

echo $user->id();

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

## Update

```php
$user = $database->repository(User::class)->find(1);
$user->deactivate();

$database->persist($user);
```

`persist()` updates an entity when its ID is not null.

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
