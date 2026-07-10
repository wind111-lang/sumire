# Sumire

Sumire is a small PDO Mapper for PHP 8.2+.

It is designed for projects that want explicit database access without bringing in a large database framework. PDO owns the connection and SQL execution. PHP attributes describe how a class maps to a table. Sumire adds a small persistence API, repository helpers, typed parameter binding, and predictable behavior across SQLite, MySQL, and PostgreSQL.

## Goals

- Keep the runtime dependency surface small.
- Make entity mapping explicit with PHP attributes.
- Provide enough CRUD behavior for small applications and libraries.
- Stay close to PDO instead of hiding the database completely.
- Support SQLite, MySQL, and PostgreSQL with the same public API.

## Non-Goals

Sumire is intentionally small. These features are not part of the current scope:

- Relationship loading
- Query builder DSL
- Schema migrations
- Unit of work tracking
- Lazy loading proxies
- Change detection

## Quick Example

```php
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\Database;

#[Table('users')]
final class User
{
    #[Id]
    private ?int $id = null;

    #[Column]
    private string $name;

    #[Column]
    private string $email;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }
}

$database = Database::connect(new PDO('sqlite::memory:'));

$user = new User('Ada Lovelace', 'ada@example.com');
$database->persist($user);

$found = $database->repository(User::class)->find(1);
```

## Documentation

- [Getting Started](Getting-Started)
- [Entity Mapping](Entity-Mapping)
- [Database API](Database-API)
- [Repository API](Repository-API)
- [Connection API](Connection-API)
- [Examples](Examples)
- [Database Notes](Database-Notes)
- [Development](Development)
