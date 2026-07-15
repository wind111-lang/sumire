# Sumire

Sumire is a small PDO Mapper for PHP 8.2+.

PDO handles the database connection, PHP attributes describe your entities, and Sumire provides a focused persistence and repository layer on top.

## Installation

```bash
composer require wind111-lang/sumire
```

## Requirements

- PHP 8.2+
- `ext-pdo`
- One PDO driver for your database:
  - SQLite: `pdo_sqlite`
  - MySQL: `pdo_mysql`
  - PostgreSQL: `pdo_pgsql`

## Quick Start

The following example uses an in-memory SQLite database, so it can be run as a single PHP script.

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\Database;
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

$pdo = new PDO('sqlite::memory:');
$pdo->exec(<<<'SQL'
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        active INTEGER NOT NULL
    )
    SQL);

$database = Database::connect($pdo);

// Insert a new entity. The generated ID is written back to $user.
$user = new User('Ada Lovelace', 'ada@example.com');
$database->persist($user);

$users = $database->repository(User::class);

// Find one entity by its primary key.
$found = $users->find($user->id());

// Query by mapped properties and order the results.
$activeUsers = $users->findBy(
    criteria: ['active' => true],
    orderBy: ['name' => 'ASC'],
);

// Saving an entity with an ID performs an update.
$user->deactivate();
$users->save($user);
```

Sumire maps existing tables; schema creation and migrations remain the application's responsibility. The `CREATE TABLE` statement above is included only to make the example immediately runnable.

## Domain Model Conversion

`DomainMapper` binds a mapped class, a domain class, and both conversion callables. The mapper can then be reused without adding Sumire methods to either model.

```php
final readonly class DomainUser
{
    public function __construct(
        public string $name,
        public string $email,
        public bool $active,
    ) {}
}

$domainUser = new DomainUser('Grace Hopper', 'grace@example.com', true);

$mapper = DomainMapper::between(
    entityClass: User::class,
    domainClass: DomainUser::class,
    fromDomain: static fn(DomainUser $user): User => new User($user->name, $user->email, $user->active),
    toDomain: static fn(User $user): DomainUser => new DomainUser($user->name(), $user->email(), $user->active()),
);

$entity = $mapper->fromDomain($domainUser);
$mappedDomainUser = $mapper->toDomain($entity);
```

## More Queries

Repositories provide common lookups, criteria operators, counts, existence checks, and pagination.

```php
$matchingUsers = $users->findBy([
    'id >' => 0,
    'email LIKE' => '%@example.com',
]);

$emailTaken = $users->exists(['email' => 'ada@example.com']);
$activeCount = $users->count(['active' => true]);

$page = $users->paginate(
    criteria: ['active' => true],
    orderBy: ['name' => 'ASC'],
    limit: 20,
    offset: 0,
);

$items = $page->items;
$total = $page->total;
```

Use `Database::transaction()` when several writes must succeed or fail together.

```php
$database->transaction(function (Database $database): void {
    $database->persist(new User('Grace Hopper', 'grace@example.com'));
    $database->persist(new User('Katherine Johnson', 'katherine@example.com'));
});
```

## Documentation

The main documentation lives in the GitHub Wiki:

- [Overview](https://github.com/wind111-lang/sumire/wiki)
- [Getting Started](https://github.com/wind111-lang/sumire/wiki/Getting-Started)
- [Entity Mapping](https://github.com/wind111-lang/sumire/wiki/Entity-Mapping)
- [Database API](https://github.com/wind111-lang/sumire/wiki/Database-API)
- [Repository API](https://github.com/wind111-lang/sumire/wiki/Repository-API)
- [Connection API](https://github.com/wind111-lang/sumire/wiki/Connection-API)
- [Examples](https://github.com/wind111-lang/sumire/wiki/Examples)
- [Database Notes](https://github.com/wind111-lang/sumire/wiki/Database-Notes)
- [Development](https://github.com/wind111-lang/sumire/wiki/Development)

The Wiki source is mirrored in [`docs/wiki`](docs/wiki) so changes can be reviewed through pull requests.

## Development

```bash
composer dump-autoload
composer ci
```

For the full local workflow, see [Development](https://github.com/wind111-lang/sumire/wiki/Development).
