# Sumire

Sumire is a small PDO Mapper for PHP 8.2+.

It keeps the runtime surface intentionally small: PDO handles the database connection, PHP attributes describe your entities, and Sumire provides a light persistence and repository layer on top.

```php
use Sumire\Database;

$database = Database::connect(new PDO('sqlite::memory:'));
$database->persist($user);

$found = $database->repository(User::class)->find(1);
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

## Development

```bash
composer dump-autoload
composer ci
```

For the full local workflow, see [Development](https://github.com/wind111-lang/sumire/wiki/Development).
