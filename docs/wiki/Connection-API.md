# Connection API

`Sumire\Connection` is a small wrapper around PDO.

Most application code should use `Database`, but `Connection` is useful for schema setup, raw SQL, and integration with existing SQL code.

## `__construct()`

```php
public function __construct(PDO $pdo)
```

Wraps a PDO instance and configures it with:

- `PDO::ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION`
- `PDO::ATTR_DEFAULT_FETCH_MODE = PDO::FETCH_ASSOC`

```php
$connection = new Connection(new PDO('sqlite::memory:'));
```

## `execute()`

```php
public function execute(string $sql, array $params = []): int
```

Executes a statement and returns the affected row count.

```php
$affected = $connection->execute(
    'UPDATE users SET active = :active WHERE id = :id',
    ['active' => false, 'id' => 1],
);
```

Parameters are bound with PDO types:

- `null` -> `PDO::PARAM_NULL`
- `bool` -> `PDO::PARAM_BOOL`
- `int` -> `PDO::PARAM_INT`
- everything else -> `PDO::PARAM_STR`

## `fetchOne()`

```php
public function fetchOne(string $sql, array $params = []): ?array
```

Fetches one row as an associative array.

```php
$row = $connection->fetchOne(
    'SELECT * FROM users WHERE id = :id',
    ['id' => 1],
);
```

Returns `null` when no row is found.

## `fetchAll()`

```php
public function fetchAll(string $sql, array $params = []): array
```

Fetches all rows as associative arrays.

```php
$rows = $connection->fetchAll('SELECT * FROM users ORDER BY name ASC');
```

## `driverName()`

```php
public function driverName(): string
```

Returns the PDO driver name.

```php
if ($connection->driverName() === 'pgsql') {
    // PostgreSQL-specific behavior
}
```

## `lastInsertId()`

```php
public function lastInsertId(): string
```

Returns PDO's last insert ID.

```php
$id = $connection->lastInsertId();
```

Sumire uses this for generated IDs on SQLite and MySQL.

## `quoteIdentifier()`

```php
public function quoteIdentifier(string $identifier): string
```

Quotes a table or column identifier for the current driver.

```php
$column = $connection->quoteIdentifier('users.email');
```

Driver behavior:

| Driver | Example |
| --- | --- |
| MySQL | `` `users`.`email` `` |
| SQL Server | `[users].[email]` |
| Default, including SQLite/PostgreSQL | `"users"."email"` |

## `transaction()`

```php
public function transaction(callable $callback): mixed
```

Runs a callback inside a transaction.

```php
$connection->transaction(function (Connection $connection): void {
    $connection->execute('INSERT INTO logs (message) VALUES (:message)', [
        'message' => 'created',
    ]);
});
```

If the callback throws, the transaction is rolled back and the original exception is rethrown.

## Nested Transactions

`Connection::transaction()` supports nested calls with savepoints.

```php
$connection->transaction(function (Connection $connection): void {
    $connection->execute('INSERT INTO users (name) VALUES (:name)', [
        'name' => 'outer',
    ]);

    try {
        $connection->transaction(function (Connection $connection): void {
            $connection->execute('INSERT INTO users (name) VALUES (:name)', [
                'name' => 'inner',
            ]);

            throw new RuntimeException('rollback inner work only');
        });
    } catch (RuntimeException) {
        // The outer transaction is still active.
    }
});
```

Behavior:

- The outermost transaction uses `PDO::beginTransaction()`, `commit()`, and `rollBack()`.
- Nested transactions use `SAVEPOINT`, `RELEASE SAVEPOINT`, and `ROLLBACK TO SAVEPOINT`.
- A successful nested transaction releases its savepoint; it does not commit the outer transaction.
- A failed nested transaction rolls back to its savepoint and rethrows the original exception.
- If the outer transaction fails, all nested work is rolled back too.

Do not manually run `COMMIT` or `ROLLBACK` inside a `transaction()` callback. Sumire expects the callback to leave the transaction open until it returns or throws.
