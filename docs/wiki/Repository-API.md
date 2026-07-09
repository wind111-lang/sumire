# Repository API

`Sumire\EntityRepository` provides a typed helper around `Database` for one entity class.

You usually create it through `Database::repository()`.

```php
$users = $database->repository(User::class);
```

## `find()`

```php
public function find(mixed $id): ?object
```

Finds one entity by primary key.

```php
$user = $users->find(1);
```

Returns `null` when no row is found.

## `findBy()`

```php
public function findBy(
    array $criteria = [],
    array $orderBy = [],
    ?int $limit = null,
    ?int $offset = null,
): array
```

Finds entities matching criteria.

```php
$activeUsers = $users->findBy(
    ['active' => true],
    ['name' => 'ASC'],
    limit: 20,
);
```

## `firstBy()`

```php
public function firstBy(array $criteria = [], array $orderBy = []): ?object
```

Returns the first matching entity.

```php
$ada = $users->firstBy(['email' => 'ada@example.com']);
```

This is equivalent to `findBy($criteria, $orderBy, 1)[0] ?? null`.

## `all()`

```php
public function all(): array
```

Returns all rows for the repository entity.

```php
$allUsers = $users->all();
```

Use this only when the table is small or when you intentionally need all rows.

## `count()`

```php
public function count(array $criteria = []): int
```

Returns the number of rows matching criteria.

```php
$activeCount = $users->count(['active' => true]);
```

## `exists()`

```php
public function exists(array $criteria = []): bool
```

Returns whether at least one row matches criteria.

```php
if ($users->exists(['email' => 'ada@example.com'])) {
    // A matching row exists.
}
```

## `save()`

```php
public function save(object $entity): void
```

Persists an entity through the repository.

```php
$users->save($user);
```

This delegates to `Database::persist()`.

## `remove()`

```php
public function remove(object $entity): void
```

Deletes an entity through the repository.

```php
$users->remove($user);
```

This delegates to `Database::remove()`.
