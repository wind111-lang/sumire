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
public function insert(object $entity): void
```

Inserts an entity.

```php
$database->insert($user);
```

For generated IDs:

- SQLite/MySQL use `PDO::lastInsertId()`.
- PostgreSQL uses `INSERT ... RETURNING`.

## `update()`

```php
public function update(object $entity): void
```

Updates an entity by its ID.

```php
$user->rename('Ada King');
$database->update($user);
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
