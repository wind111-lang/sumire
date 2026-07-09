# Entity Mapping

Sumire maps PHP classes to database rows with attributes.

## `#[Table]`

Use `#[Table]` on an entity class to define the database table.

```php
use Sumire\Attributes\Table;

#[Table('users')]
final class User
{
}
```

### Constructor

```php
public function __construct(string $name)
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` | Table name. |

If `#[Table]` is omitted, Sumire uses the class short name converted to snake case. For example, `BlogPost` maps to `blog_post`.

## `#[Id]`

Use `#[Id]` on the primary key property.

```php
use Sumire\Attributes\Id;

#[Id]
private ?int $id = null;
```

### Constructor

```php
public function __construct(?string $name = null, bool $generated = true)
```

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `$name` | `?string` | `null` | Column name. If omitted, the property name is converted to snake case. |
| `$generated` | `bool` | `true` | Whether Sumire should read the generated ID after insert. |

Only one `#[Id]` property is supported per entity.

## `#[Column]`

Use `#[Column]` on regular mapped properties.

```php
use Sumire\Attributes\Column;

#[Column]
private string $email;
```

### Constructor

```php
public function __construct(?string $name = null, bool $nullable = false)
```

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `$name` | `?string` | `null` | Column name. If omitted, the property name is converted to snake case. |
| `$nullable` | `bool` | `false` | Documents whether the column can be null. |

## Naming Defaults

When an attribute does not specify a column name, Sumire converts the property name to snake case.

```php
#[Column]
private string $createdAt;
```

This maps to `created_at`.

## Hydration

Sumire hydrates entities without calling the constructor. It sets mapped properties through reflection. This lets you keep constructors focused on new objects while still reading existing rows from the database.

```php
$user = $database->find(User::class, 1);
```

## Type Casting

Sumire casts scalar values to the declared property type during hydration:

- `int`
- `float`
- `bool`
- `string`

Boolean casting accepts common database values:

- `1`, `true`, `t`, `on`, `yes` -> `true`
- `0`, `false`, `f`, `off`, `no`, empty string -> `false`

## Mapping Rules

- An entity must define at least one mapped property.
- An entity must define exactly one `#[Id]` property.
- Unattributed properties are ignored.
- Composite primary keys are not currently supported.
- Relationships are not currently supported.
