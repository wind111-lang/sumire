<?php

declare(strict_types=1);

namespace Sumire\Mapping;

use ReflectionNamedType;
use ReflectionProperty;

final readonly class PropertyMapping
{
    public function __construct(
        public string $propertyName,
        public string $columnName,
        public ReflectionProperty $property,
        public bool $id = false,
        public bool $generated = false,
        public bool $nullable = false,
    ) {}

    public function getValue(object $entity): mixed
    {
        if (!$this->property->isInitialized($entity)) {
            return null;
        }

        return $this->property->getValue($entity);
    }

    public function setValue(object $entity, mixed $value): void
    {
        $this->property->setValue($entity, $this->castValue($value));
    }

    private function castValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->property->getType();
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => $this->castBool($value),
            'string' => (string) $value,
            default => $value,
        };
    }

    private function castBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0 && $value !== 0.0;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '', '0', 'false', 'f', 'off', 'no' => false,
                '1', 'true', 't', 'on', 'yes' => true,
                default => (bool) $value,
            };
        }

        return (bool) $value;
    }
}
