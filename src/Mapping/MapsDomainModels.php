<?php

declare(strict_types=1);

namespace Sumire\Mapping;

use Sumire\Exception\MappingException;

trait MapsDomainModels
{
    /**
     * @template TDomain of object
     * @param TDomain $model
     * @param callable(TDomain): static $mapper
     */
    public static function fromDomain(object $model, callable $mapper): static
    {
        $mapped = self::invokeDomainModelMapper($model, $mapper);
        $class = static::class;

        if (!$mapped instanceof $class) {
            throw new MappingException(sprintf(
                'fromDomain() mapper for "%s" must return an instance of "%s"; "%s" returned.',
                $class,
                $class,
                get_debug_type($mapped),
            ));
        }

        return $mapped;
    }

    /**
     * @template TDomain of object
     * @param callable(static): TDomain $mapper
     * @return TDomain
     */
    public static function toDomain(object $model, callable $mapper): object
    {
        $class = static::class;

        if (!$model instanceof $class) {
            throw new MappingException(sprintf(
                'toDomain() expects an instance of "%s"; "%s" given.',
                $class,
                get_debug_type($model),
            ));
        }

        $mapped = self::invokeDomainModelMapper($model, $mapper);

        if (!is_object($mapped)) {
            throw new MappingException(sprintf(
                'toDomain() mapper for "%s" must return an object; "%s" returned.',
                $class,
                get_debug_type($mapped),
            ));
        }

        return $mapped;
    }

    private static function invokeDomainModelMapper(object $model, callable $mapper): mixed
    {
        return $mapper($model);
    }
}
