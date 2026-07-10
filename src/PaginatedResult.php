<?php

declare(strict_types=1);

namespace Sumire;

use InvalidArgumentException;

/**
 * @template T of object
 */
final readonly class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException('Total must be greater than or equal to zero.');
        }

        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be greater than zero.');
        }

        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be greater than or equal to zero.');
        }
    }

    public function hasNextPage(): bool
    {
        return $this->offset + $this->limit < $this->total;
    }

    public function hasPreviousPage(): bool
    {
        return $this->offset > 0;
    }
}
