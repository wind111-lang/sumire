<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\Database;
use Sumire\Exception\MappingException;
use Sumire\Tests\Fixtures\Post;

final class SchemaManagerTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        $this->database = Database::connect(new PDO('sqlite::memory:'));
    }

    public function testCreatesTableFromMappedPropertyTypes(): void
    {
        $this->database->createTable(Post::class);

        $columns = $this->database->connection()->fetchAll('PRAGMA table_info("posts")');

        self::assertSame(
            ['id', 'title', 'status', 'metadata', 'created_at'],
            array_column($columns, 'name'),
        );
        self::assertSame(
            ['INTEGER', 'TEXT', 'TEXT', 'TEXT', 'TEXT'],
            array_column($columns, 'type'),
        );
        self::assertSame(1, $columns[0]['pk']);
        self::assertSame(1, $columns[1]['notnull']);
    }

    public function testCanIgnoreAnExistingTableExplicitly(): void
    {
        $this->database->createTable(Post::class);
        $this->database->createTable(Post::class, ifNotExists: true);

        self::assertSame(0, $this->database->count(Post::class));
    }

    public function testFailsWhenTableAlreadyExistsByDefault(): void
    {
        $this->database->createTable(Post::class);

        $this->expectException(PDOException::class);

        $this->database->createTable(Post::class);
    }

    public function testRejectsAColumnWithoutAnInferableType(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Cannot infer a database type');

        $this->database->createTable(UnsupportedColumnEntity::class);
    }

    public function testRejectsANonIntegerGeneratedId(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('must have type int');

        $this->database->createTable(UnsupportedGeneratedIdEntity::class);
    }
}

#[Table('unsupported_columns')]
final class UnsupportedColumnEntity
{
    #[Id]
    private ?int $id = null;

    /** @var list<string> */
    #[Column]
    private array $values = [];

    /** @param list<string> $values */
    public function __construct(?int $id = null, array $values = [])
    {
        $this->id = $id;
        $this->values = $values;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /** @return list<string> */
    public function values(): array
    {
        return $this->values;
    }
}

#[Table('unsupported_generated_ids')]
final class UnsupportedGeneratedIdEntity
{
    #[Id]
    private ?string $id = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id;
    }

    public function id(): ?string
    {
        return $this->id;
    }
}
