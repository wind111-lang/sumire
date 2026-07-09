<?php

declare(strict_types=1);

namespace Sumire\Attributes;

use Attribute;
use Sumire\ColumnType;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public bool $nullable = false,
        public ?ColumnType $type = null,
    ) {}
}
