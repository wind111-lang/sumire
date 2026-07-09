<?php

declare(strict_types=1);

namespace Sumire\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Id
{
    public function __construct(
        public ?string $name = null,
        public bool $generated = true,
    ) {}
}
