<?php

declare(strict_types=1);

namespace Sumire\Mapping;

use ReflectionClass;
use ReflectionProperty;
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\Exception\MappingException;

final class MetadataFactory
{
    /** @var array<class-string, EntityMetadata> */
    private array $cache = [];

    /** @param class-string $className */
    public function for(string $className): EntityMetadata
    {
        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        if (!class_exists($className)) {
            throw new MappingException(sprintf('Entity class "%s" does not exist.', $className));
        }

        $reflection = new ReflectionClass($className);
        $table = $this->resolveTableName($reflection);
        $properties = [];
        $id = null;

        foreach ($reflection->getProperties() as $property) {
            $idAttribute = $this->attribute($property, Id::class);
            $columnAttribute = $this->attribute($property, Column::class);

            if ($idAttribute === null && $columnAttribute === null) {
                continue;
            }

            $columnName = $idAttribute->name
                ?? $columnAttribute->name
                ?? $this->toSnakeCase($property->getName());

            $mapping = new PropertyMapping(
                propertyName: $property->getName(),
                columnName: $columnName,
                property: $property,
                id: $idAttribute !== null,
                generated: $idAttribute->generated ?? false,
                nullable: $columnAttribute->nullable ?? false,
                type: $columnAttribute->type ?? null,
            );

            if ($mapping->id) {
                if ($id !== null) {
                    throw new MappingException(sprintf('Entity "%s" must not define more than one id.', $className));
                }

                $id = $mapping;
            }

            $properties[] = $mapping;
        }

        if ($properties === []) {
            throw new MappingException(sprintf('Entity "%s" does not define any mapped properties.', $className));
        }

        if (!$id instanceof PropertyMapping) {
            throw new MappingException(sprintf('Entity "%s" must define an #[Id] property.', $className));
        }

        return $this->cache[$className] = new EntityMetadata($className, $table, $properties, $id);
    }

    /**
     * @template T of object
     * @param class-string<T> $attribute
     * @return T|null
     */
    private function attribute(ReflectionProperty $property, string $attribute): ?object
    {
        $attributes = $property->getAttributes($attribute);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /** @param ReflectionClass<object> $reflection */
    private function resolveTableName(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(Table::class);
        if ($attributes !== []) {
            return $attributes[0]->newInstance()->name;
        }

        return $this->toSnakeCase($reflection->getShortName());
    }

    private function toSnakeCase(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);

        return strtolower($snake ?? $name);
    }
}
