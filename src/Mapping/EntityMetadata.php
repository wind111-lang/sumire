<?php

declare(strict_types=1);

namespace Sumire\Mapping;

use ReflectionClass;
use Sumire\Exception\MappingException;

final class EntityMetadata
{
    /** @var list<PropertyMapping> */
    private array $properties;

    /** @var array<string, PropertyMapping> */
    private array $fieldMap = [];

    /**
     * @param class-string $className
     * @param list<PropertyMapping> $properties
     */
    public function __construct(
        public readonly string $className,
        public readonly string $tableName,
        array $properties,
        private readonly PropertyMapping $id,
    ) {
        $this->properties = $properties;

        foreach ($properties as $property) {
            $this->fieldMap[$property->propertyName] = $property;
            $this->fieldMap[$property->columnName] = $property;
        }
    }

    public function id(): PropertyMapping
    {
        return $this->id;
    }

    /** @return list<PropertyMapping> */
    public function properties(): array
    {
        return $this->properties;
    }

    /** @return list<PropertyMapping> */
    public function insertableProperties(object $entity): array
    {
        return array_values(array_filter(
            $this->properties,
            static fn(PropertyMapping $mapping): bool => !$mapping->id || !$mapping->generated || $mapping->getValue($entity) !== null,
        ));
    }

    /** @return list<PropertyMapping> */
    public function updatableProperties(): array
    {
        return array_values(array_filter(
            $this->properties,
            static fn(PropertyMapping $mapping): bool => !$mapping->id,
        ));
    }

    public function mappingForField(string $field): PropertyMapping
    {
        return $this->fieldMap[$field]
            ?? throw new MappingException(sprintf('Field "%s" is not mapped on entity "%s".', $field, $this->className));
    }

    /** @param array<string, mixed> $row */
    public function hydrate(array $row): object
    {
        $entity = (new ReflectionClass($this->className))->newInstanceWithoutConstructor();

        foreach ($this->properties as $mapping) {
            if (array_key_exists($mapping->columnName, $row)) {
                $mapping->setValue($entity, $row[$mapping->columnName]);
            }
        }

        return $entity;
    }
}
