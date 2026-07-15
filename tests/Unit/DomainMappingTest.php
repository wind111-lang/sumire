<?php

declare(strict_types=1);

namespace Sumire\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sumire\Exception\MappingException;
use Sumire\Mapping\DomainMapper;
use Sumire\Tests\Fixtures\DomainUser;
use Sumire\Tests\Fixtures\User;

final class DomainMappingTest extends TestCase
{
    /** @var DomainMapper<User, DomainUser> */
    private DomainMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = DomainMapper::between(
            entityClass: User::class,
            domainClass: DomainUser::class,
            fromDomain: static fn(DomainUser $user): User => new User($user->name, $user->email, $user->active),
            toDomain: static fn(User $user): DomainUser => new DomainUser($user->name(), $user->email(), $user->active()),
        );
    }

    public function testMapsFromAndToDomainModels(): void
    {
        $domain = new DomainUser('Ada Lovelace', 'ada@example.com', true);

        $entity = $this->mapper->fromDomain($domain);

        self::assertSame('Ada Lovelace', $entity->name());
        self::assertSame('ada@example.com', $entity->email());
        self::assertTrue($entity->active());

        self::assertEquals($domain, $this->mapper->toDomain($entity));
    }

    public function testRejectsFromDomainInputFromWrongClass(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('fromDomain() input');

        $method = new ReflectionMethod($this->mapper, 'fromDomain');
        $method->invoke($this->mapper, new User('Ada Lovelace', 'ada@example.com'));
    }

    public function testRejectsFromDomainMapperReturningWrongClass(): void
    {
        $domain = new DomainUser('Ada Lovelace', 'ada@example.com', true);
        $mapper = DomainMapper::between(
            User::class,
            DomainUser::class,
            $this->mapperReturning($domain),
            static fn(User $user): DomainUser => new DomainUser($user->name(), $user->email(), $user->active()),
        );

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('fromDomain() mapper result');

        $mapper->fromDomain($domain);
    }

    public function testRejectsToDomainInputFromWrongClass(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('toDomain() input');

        $method = new ReflectionMethod($this->mapper, 'toDomain');
        $method->invoke($this->mapper, new DomainUser('Ada Lovelace', 'ada@example.com', true));
    }

    public function testRejectsToDomainMapperReturningWrongClass(): void
    {
        $mapper = DomainMapper::between(
            User::class,
            DomainUser::class,
            static fn(DomainUser $user): User => new User($user->name, $user->email, $user->active),
            $this->mapperReturning('invalid'),
        );

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('toDomain() mapper result');

        $mapper->toDomain(new User('Ada Lovelace', 'ada@example.com'));
    }

    private function mapperReturning(mixed $value): callable
    {
        return static fn(object $_model): mixed => $value;
    }
}
