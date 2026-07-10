<?php

declare(strict_types=1);

namespace Sumire\Mapping;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;
use Sumire\ColumnType;
use Sumire\Exception\MappingException;

final readonly class PropertyMapping
{
    public function __construct(
        public string $propertyName,
        public string $columnName,
        public ReflectionProperty $property,
        public bool $id = false,
        public bool $generated = false,
        public bool $nullable = false,
        public ?ColumnType $type = null,
    ) {}

    public function getValue(object $entity): mixed
    {
        if (!$this->property->isInitialized($entity)) {
            return null;
        }

        return $this->property->getValue($entity);
    }

    public function getDatabaseValue(object $entity): mixed
    {
        return $this->toDatabaseValue($this->getValue($entity));
    }

    public function toDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->type === ColumnType::Json) {
            return $this->encodeJson($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
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
        if ($this->type === ColumnType::Json) {
            return $this->decodeJson($value, $type instanceof ReflectionNamedType ? $type->getName() : null);
        }

        if ($this->type === ColumnType::DateTime || $this->isDateTimeType($type)) {
            return $this->castDateTime($value, $type instanceof ReflectionNamedType ? $type->getName() : DateTimeImmutable::class);
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && enum_exists($type->getName())) {
            return $this->castEnum($value, $type->getName());
        }

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

    private function isDateTimeType(?\ReflectionType $type): bool
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $typeName = $type->getName();

        return $typeName === DateTimeInterface::class
            || $typeName === DateTimeImmutable::class
            || $typeName === DateTime::class
            || is_subclass_of($typeName, DateTimeInterface::class);
    }

    private function castDateTime(mixed $value, string $typeName): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            if ($typeName === DateTime::class && !$value instanceof DateTime) {
                return new DateTime($value->format(DateTimeInterface::ATOM));
            }

            if (($typeName === DateTimeImmutable::class || $typeName === DateTimeInterface::class) && !$value instanceof DateTimeImmutable) {
                return new DateTimeImmutable($value->format(DateTimeInterface::ATOM));
            }

            return $value;
        }

        if ($typeName === DateTime::class) {
            return new DateTime((string) $value);
        }

        return new DateTimeImmutable((string) $value);
    }

    /**
     * @param class-string $enumClass
     */
    private function castEnum(mixed $value, string $enumClass): BackedEnum
    {
        if ($value instanceof $enumClass) {
            if (!$value instanceof BackedEnum) {
                throw new MappingException(sprintf('Enum property "%s" must use a backed enum.', $this->propertyName));
            }

            return $value;
        }

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new MappingException(sprintf('Enum property "%s" must use a backed enum.', $this->propertyName));
        }

        return $enumClass::from($value);
    }

    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MappingException(sprintf('Could not encode JSON for property "%s": %s', $this->propertyName, $exception->getMessage()), previous: $exception);
        }
    }

    private function decodeJson(mixed $value, ?string $typeName): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        try {
            return json_decode(
                $value,
                associative: $typeName !== stdClass::class && $typeName !== 'object',
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new MappingException(sprintf('Could not decode JSON for property "%s": %s', $this->propertyName, $exception->getMessage()), previous: $exception);
        }
    }
}
