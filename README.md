# Sumire

Sumire is a small PDO mapper for PHP 8.2+.

It keeps the runtime surface intentionally small: PDO does the database work, PHP attributes describe your entities, and Sumire provides a light repository and persistence layer on top.

## Features

- Attribute-based entity mapping
- `insert`, `update`, `delete`, `find`, and `findBy`
- Repository API
- Transaction helper
- SQLite, MySQL, and PostgreSQL identifier quoting
- PostgreSQL generated IDs via `INSERT ... RETURNING`
- Typed PDO parameter binding
- Runtime dependency limited to `ext-pdo`

## Supported Databases

Sumire is a thin layer over PDO. Install the PDO driver for the database you want to use:

- SQLite: `pdo_sqlite`
- MySQL: `pdo_mysql`
- PostgreSQL: `pdo_pgsql`

Generated IDs are read with `PDO::lastInsertId()` on SQLite/MySQL and `RETURNING` on PostgreSQL.

## Installation

```bash
composer require wind111-lang/sumire
```

## Usage

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

If you need direct access to the low-level wrapper, call `connection()`:

```php
$database->connection()->execute('CREATE TABLE users (...)');
```

## Development

```bash
composer dump-autoload
composer lint
composer test
composer analyse
composer cs:check
composer ci
```

Apply coding-standard fixes with:

```bash
composer cs:fix
```

## Integration Smoke Tests

MySQL and PostgreSQL smoke tests run against a real database by passing a DSN:

```bash
SUMIRE_DRIVER=mysql \
SUMIRE_DSN='mysql:host=127.0.0.1;port=3306;dbname=sumire_test;charset=utf8mb4' \
SUMIRE_USER=root \
SUMIRE_PASSWORD=secret \
composer test:integration

SUMIRE_DRIVER=pgsql \
SUMIRE_DSN='pgsql:host=127.0.0.1;port=5432;dbname=sumire_test' \
SUMIRE_USER=postgres \
SUMIRE_PASSWORD=secret \
composer test:integration
```
