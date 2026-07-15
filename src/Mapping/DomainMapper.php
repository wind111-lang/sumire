<?php

declare(strict_types=1);

namespace Sumire\Mapping;

use Closure;
use Sumire\Exception\MappingException;

/**
 * @template TEntity of object
 * @template TDomain of object
 */
final readonly class DomainMapper
{
    /** @var class-string<TEntity> */
    private string $entityClass;

    /** @var class-string<TDomain> */
    private string $domainClass;

    /** @var Closure(TDomain): TEntity */
    private Closure $fromDomainMapper;

    /** @var Closure(TEntity): TDomain */
    private Closure $toDomainMapper;

    /**
     * @param class-string<TEntity> $entityClass
     * @param class-string<TDomain> $domainClass
     * @param callable(TDomain): TEntity $fromDomain
     * @param callable(TEntity): TDomain $toDomain
     */
    private function __construct(string $entityClass, string $domainClass, callable $fromDomain, callable $toDomain)
    {
        $this->entityClass = $entityClass;
        $this->domainClass = $domainClass;
        $this->fromDomainMapper = Closure::fromCallable($fromDomain);
        $this->toDomainMapper = Closure::fromCallable($toDomain);
    }

    /**
     * @template TEntityType of object
     * @template TDomainType of object
     * @param class-string<TEntityType> $entityClass
     * @param class-string<TDomainType> $domainClass
     * @param callable(TDomainType): TEntityType $fromDomain
     * @param callable(TEntityType): TDomainType $toDomain
     * @return self<TEntityType, TDomainType>
     */
    public static function between(
        string $entityClass,
        string $domainClass,
        callable $fromDomain,
        callable $toDomain,
    ): self {
        return new self($entityClass, $domainClass, $fromDomain, $toDomain);
    }

    /**
     * @param TDomain $model
     * @return TEntity
     */
    public function fromDomain(object $model): object
    {
        $domain = self::requireInstanceOf($model, $this->domainClass, 'fromDomain() input');
        $mapped = self::invokeMapper($domain, $this->fromDomainMapper);

        return self::requireInstanceOf($mapped, $this->entityClass, 'fromDomain() mapper result');
    }

    /**
     * @param TEntity $model
     * @return TDomain
     */
    public function toDomain(object $model): object
    {
        $entity = self::requireInstanceOf($model, $this->entityClass, 'toDomain() input');
        $mapped = self::invokeMapper($entity, $this->toDomainMapper);

        return self::requireInstanceOf($mapped, $this->domainClass, 'toDomain() mapper result');
    }

    private static function invokeMapper(object $model, Closure $mapper): mixed
    {
        return $mapper($model);
    }

    /**
     * @template TObject of object
     * @param class-string<TObject> $expectedClass
     * @return TObject
     */
    private static function requireInstanceOf(mixed $value, string $expectedClass, string $context): object
    {
        if (!$value instanceof $expectedClass) {
            throw new MappingException(sprintf(
                '%s must be an instance of "%s"; "%s" given.',
                $context,
                $expectedClass,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}
