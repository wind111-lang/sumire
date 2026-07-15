<?php

declare(strict_types=1);

namespace Sumire\Tests\Fixtures;

final readonly class DomainUser
{
    public function __construct(
        public string $name,
        public string $email,
        public bool $active,
    ) {}
}
