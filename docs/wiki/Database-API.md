# Database API

`Sumire\Database` is the main entry point for application code.

## `Database::connect()`

```php
public static function connect(PDO $pdo, ?MetadataFactory $metadataFactory = null): self
```

Creates a `Database` instance from an existing PDO connection.

```php
$database = Database::connect(new PDO('sqlite::memory:'));
```

Use this method for normal application setup.

## `__construct()`

```php
public function __construct(Connection $connection, ?MetadataFactory $metadataFactory = null)
```

Creates a `Database` from a Sumire `Connection`.

```php
$connection = new Connection($pdo);
$database = new Database($connection);
```

Most applications should prefer `Database::connect()`.

## `connection()`

```php
public function connection(): Connection
```

Returns the low-level Sumire connection wrapper.

```php
$database->connection()->execute('DELETE FROM users WHERE active = :active', [
    'active' => false,
]);
```

## `repository()`

```php
public function repository(string $entityClass): EntityRepository
```

Creates a repository for an entity class.

```php
$users = $database->repository(User::class);
$activeUsers = $users->findBy(['active' => true]);
```

## `find()`

```php
public function find(string $entityClass, mixed $id): ?object
```

Finds one entity by primary key.

```php
$user = $database->find(User::class, 1);
```

Returns `null` when no row is found.

## `findBy()`

```php
public function findBy(
    string $entityClass,
    array $criteria = [],
    array $orderBy = [],
    ?int $limit = null,
    ?int $offset = null,
): array
```

Finds entities matching field criteria.

```php
$users = $database->findBy(
    User::class,
    ['active' => true],
    ['name' => 'ASC'],
    limit: 10,
);
```

Criteria keys can be property names or column names.

```php
$users = $database->findBy(User::class, [
    'email' => 'ada@example.com',
]);
```

Criteria values support:

| Value | SQL behavior |
| --- | --- |
| Scalar | `column = :value` |
| `null` | `column IS NULL` |
| Non-empty array | `column IN (...)` |
| Empty array | `1 = 0` |

## `paginate()`

```php
public function paginate(
    string $entityClass,
    array $criteria = [],
    array $orderBy = [],
    int $limit = 50,
    int $offset = 0,
): PaginatedResult
```

Returns one page of entities plus pagination metadata.

```php
$page = $database->paginate(
    User::class,
    criteria: ['active' => true],
    orderBy: ['name' => 'ASC'],
    limit: 20,
    offset: 40,
);

$items = $page->items;
$total = $page->total;
```

`limit` must be greater than zero. `offset` must be greater than or equal to zero.

`PaginatedResult` exposes:

| Property or method | Description |
| --- | --- |
| `$items` | Current page items. |
| `$total` | Total rows matching the criteria. |
| `$limit` | Requested page size. |
| `$offset` | Requested offset. |
| `hasNextPage()` | Whether another page exists after this one. |
| `hasPreviousPage()` | Whether a previous page exists before this one. |

## `persist()`

```php
public function persist(object $entity): void
```

Inserts or updates an entity.

```php
$user = new User('Ada Lovelace', 'ada@example.com');
$database->persist($user);
```

If the entity has a generated ID and the ID value is `null`, Sumire inserts it. Otherwise, Sumire updates it.

## `insert()`

```php
public function insert(object $entity): mixed
```

Inserts an entity and returns the entity ID value after insert.

```php
$id = $database->insert($user);
```

For generated IDs:

- SQLite/MySQL use `PDO::lastInsertId()`.
- PostgreSQL uses `INSERT ... RETURNING`.

When the ID is not generated, `insert()` returns the current mapped ID value.

## `update()`

```php
public function update(object $entity): int
```

Updates an entity by its ID and returns the affected row count.

```php
$user->rename('Ada King');
$affectedRows = $database->update($user);
```

Throws `Sumire\Exception\SumireException` when the ID value is `null`.

## `remove()`

```php
public function remove(object $entity): void
```

Deletes an entity by its ID.

```php
$database->remove($user);
```

Throws `Sumire\Exception\SumireException` when the ID value is `null`.

## `transaction()`

```php
public function transaction(callable $callback): mixed
```

Runs a callback inside a transaction.

```php
$database->transaction(function (Database $database): void {
    $database->persist(new User('Ada Lovelace', 'ada@example.com'));
    $database->persist(new User('Grace Hopper', 'grace@example.com'));
});
```

If the callback throws, Sumire rolls back and rethrows the original exception.

## Nested Transactions

`Database::transaction()` supports nested calls through `Connection` savepoints.

```php
$database->transaction(function (Database $database): void {
    $database->persist(new User('Outer Commit', 'outer@example.com'));

    try {
        $database->transaction(function (Database $database): void {
            $database->persist(new User('Inner Rollback', 'inner@example.com'));

            throw new RuntimeException('rollback inner work only');
        });
    } catch (RuntimeException) {
        // The outer transaction can continue.
    }

    $database->persist(new User('After Inner Rollback', 'after@example.com'));
});
```

Behavior:

- The outermost transaction commits only after the outer callback returns.
- Nested successful callbacks release their savepoint; they do not commit independently.
- Nested failed callbacks roll back to their savepoint and rethrow.
- If the outer callback throws, all nested work is rolled back.
