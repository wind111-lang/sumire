<?php

declare(strict_types=1);

namespace Sumire\Tests\Fixtures;

use DateTimeImmutable;
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\ColumnType;

#[Table('posts')]
final class Post
{
    #[Id]
    private ?int $id = null;

    #[Column]
    private string $title;

    #[Column]
    private PostStatus $status;

    /** @var array<string, mixed> */
    #[Column(type: ColumnType::Json)]
    private array $metadata;

    #[Column]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(string $title, PostStatus $status, array $metadata, DateTimeImmutable $createdAt)
    {
        $this->title = $title;
        $this->status = $status;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setIdForTest(?int $id): void
    {
        $this->id = $id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function status(): PostStatus
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function revise(PostStatus $status, array $metadata): void
    {
        $this->status = $status;
        $this->metadata = $metadata;
    }
}
