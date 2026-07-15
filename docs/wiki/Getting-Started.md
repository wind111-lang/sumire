# Getting Started

## Installation

```bash
composer require wind111-lang/sumire
```

## Requirements

- PHP 8.2+
- `ext-pdo`
- A PDO driver for your database:
  - SQLite: `pdo_sqlite`
  - MySQL: `pdo_mysql`
  - PostgreSQL: `pdo_pgsql`

## Create a PDO Connection

Sumire starts from an existing PDO instance.

```php
use Sumire\Database;

$pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$database = Database::connect($pdo);
```

`Database::connect()` wraps PDO in Sumire's `Connection` class and configures PDO to use exceptions and associative arrays.

## Define an Entity

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
}
```

## Create the Table

Create a basic table from the entity metadata.

```php
$database->createTable(User::class);
```

`createTable()` is intended for examples, tests, and simple schema setup. Use a migration tool for production schema changes, indexes, unique constraints, foreign keys, and defaults.

## Persist and Read Data

```php
$user = new User('Ada Lovelace', 'ada@example.com');

$database->persist($user);

$sameUser = $database->find(User::class, $user->id());
$allUsers = $database->repository(User::class)->all();
```

For generated IDs, Sumire assigns the generated value back to the entity after insert.

## Use a Repository

```php
$users = $database->repository(User::class);

$activeUsers = $users->findBy(['active' => true], ['name' => 'ASC']);
$firstAda = $users->firstBy(['name' => 'Ada Lovelace']);
```

## Next Steps

- [Entity Mapping](Entity-Mapping)
- [Schema](Schema)
- [Database API](Database-API)
- [Examples](Examples)
