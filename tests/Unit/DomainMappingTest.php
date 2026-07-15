<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sumire\Exception\MappingException;
use Sumire\Tests\Fixtures\DomainUser;
use Sumire\Tests\Fixtures\User;

final class DomainMappingTest extends TestCase
{
    public function testMapsFromAndToDomainModels(): void
    {
        $domain = new DomainUser('Ada Lovelace', 'ada@example.com', true);

        $entity = User::fromDomain(
            $domain,
            static fn(DomainUser $user): User => new User($user->name, $user->email, $user->active),
        );

        self::assertSame('Ada Lovelace', $entity->name());
        self::assertSame('ada@example.com', $entity->email());
        self::assertTrue($entity->active());

        $mappedDomain = User::toDomain(
            $entity,
            static fn(User $user): DomainUser => new DomainUser($user->name(), $user->email(), $user->active()),
        );

        self::assertEquals($domain, $mappedDomain);
    }

    public function testRejectsFromDomainMapperReturningWrongClass(): void
    {
        $domain = new DomainUser('Ada Lovelace', 'ada@example.com', true);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('fromDomain() mapper');

        User::fromDomain($domain, $this->mapperReturning($domain));
    }

    public function testRejectsToDomainInputFromWrongClass(): void
    {
        $domain = new DomainUser('Ada Lovelace', 'ada@example.com', true);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('toDomain() expects an instance');

        User::toDomain($domain, static fn(User $_user): DomainUser => $domain);
    }

    public function testRejectsToDomainMapperReturningNonObject(): void
    {
        $entity = new User('Ada Lovelace', 'ada@example.com');

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('toDomain() mapper');

        $method = new ReflectionMethod(User::class, 'toDomain');
        $method->invoke(null, $entity, $this->mapperReturning('invalid'));
    }

    private function mapperReturning(mixed $value): callable
    {
        return static fn(object $_model): mixed => $value;
    }
}
