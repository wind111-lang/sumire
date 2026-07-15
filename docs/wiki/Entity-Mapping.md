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
public function __construct(?string $name = null, bool $nullable = false, ?ColumnType $type = null)
```

| Parameter | Type | Default | Description |
| --- | --- | --- | --- |
| `$name` | `?string` | `null` | Column name. If omitted, the property name is converted to snake case. |
| `$nullable` | `bool` | `false` | Documents whether the column can be null. |
| `$type` | `?ColumnType` | `null` | Optional conversion type for values such as JSON. |

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

Sumire also supports these typed values:

| PHP value | Mapping behavior |
| --- | --- |
| `DateTimeInterface` properties | Stored as `Y-m-d H:i:s`; hydrated as `DateTimeImmutable` or `DateTime` based on the property type. |
| Backed enum properties | Stored as the enum backing value; hydrated with `EnumClass::from()`. |
| `#[Column(type: ColumnType::Json)]` | Stored with `json_encode()`; hydrated with `json_decode()`. |

DateTime and backed enum conversion are inferred from the declared property type.

```php
use DateTimeImmutable;

#[Column]
private DateTimeImmutable $createdAt;

#[Column]
private PostStatus $status;
```

Use `ColumnType::Json` for JSON columns.

```php
use Sumire\ColumnType;

/** @var array<string, mixed> */
#[Column(type: ColumnType::Json)]
private array $metadata;
```

Criteria values and entity values both use the same conversion rules, so backed enums and DateTime objects can be passed directly to `findBy()`.

## Domain Model Conversion

Use `MapsDomainModels` when the mapped persistence class and domain model should remain separate.

```php
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\Mapping\MapsDomainModels;

#[Table('users')]
final class UserRecord
{
    use MapsDomainModels;

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

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }
}

final readonly class DomainUser
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
```

Convert a domain model into the mapped class with `fromDomain()`.

```php
$domainUser = new DomainUser('Ada Lovelace', 'ada@example.com');

$entity = UserRecord::fromDomain(
    $domainUser,
    static fn(DomainUser $user): UserRecord => new UserRecord($user->name, $user->email),
);

$database->persist($entity);
```

Convert a mapped class back into the domain model with `toDomain()`.

```php
$domainUser = UserRecord::toDomain(
    $entity,
    static fn(UserRecord $record): DomainUser => new DomainUser($record->name(), $record->email()),
);
```

The conversion callable owns the field mapping. Sumire does not copy properties automatically. `fromDomain()` requires the callable to return the mapped class. `toDomain()` requires an instance of the mapped class and an object result. Invalid mappings throw `MappingException`.

## Mapping Rules

- An entity must define at least one mapped property.
- An entity must define exactly one `#[Id]` property.
- Unattributed properties are ignored.
- Composite primary keys are not currently supported.
- Relationships are not currently supported.
