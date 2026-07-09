<?php

declare(strict_types=1);

use Sumire\Connection;
use Sumire\EntityManager;
use Sumire\Mapping\MetadataFactory;
use Sumire\Tests\Fixtures\User;

require __DIR__ . '/../vendor/autoload.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$connection = new Connection($pdo);
$entityManager = new EntityManager($connection);

$connection->execute(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    active INTEGER NOT NULL
)
SQL);

$user = new User('Ada Lovelace', 'ada@example.com');
$entityManager->persist($user);

assert_true($user->id() === 1, 'Generated id should be assigned to the entity.');

$found = $entityManager->find(User::class, $user->id());

assert_true($found instanceof User, 'Inserted user should be found by id.');
assert_true($found->name() === 'Ada Lovelace', 'Hydrated user should preserve name.');
assert_true($found->active() === true, 'Hydrated user should cast boolean values.');

$metadata = (new MetadataFactory())->for(User::class);
$postgresFalse = $metadata->hydrate([
    'id' => '10',
    'name' => 'Postgres False',
    'email' => 'postgres.false@example.com',
    'active' => 'f',
]);
$postgresTrue = $metadata->hydrate([
    'id' => '11',
    'name' => 'Postgres True',
    'email' => 'postgres.true@example.com',
    'active' => 't',
]);

assert_true($postgresFalse instanceof User, 'Hydrated false probe should be a user.');
assert_true($postgresFalse->active() === false, 'PostgreSQL "f" should hydrate as false.');
assert_true($postgresTrue instanceof User, 'Hydrated true probe should be a user.');
assert_true($postgresTrue->active() === true, 'PostgreSQL "t" should hydrate as true.');

$found->rename('Ada King');
$found->changeEmail('ada.king@example.com');
$found->deactivate();
$entityManager->persist($found);

$repository = $entityManager->repository(User::class);
$inactive = $repository->firstBy(['active' => false]);

assert_true($inactive instanceof User, 'Repository should find an inactive user.');
assert_true($inactive->email() === 'ada.king@example.com', 'Updated email should be persisted.');

$entityManager->persist(new User('Grace Hopper', 'grace@example.com'));
$ordered = $repository->findBy([], ['name' => 'DESC'], 1);

assert_true(count($ordered) === 1, 'Limit should restrict result count.');
assert_true($ordered[0]->name() === 'Grace Hopper', 'Order by should be applied.');

try {
    $entityManager->transaction(function (EntityManager $transactional): void {
        $transactional->persist(new User('Rollback Test', 'rollback@example.com'));

        throw new RuntimeException('rollback');
    });
} catch (RuntimeException $exception) {
    assert_true($exception->getMessage() === 'rollback', 'Transaction should rethrow callback exceptions.');
}

assert_true(
    $repository->firstBy(['email' => 'rollback@example.com']) === null,
    'Failed transaction should be rolled back.',
);

$entityManager->remove($inactive);

assert_true(
    $repository->find($inactive->id()) === null,
    'Removed entity should no longer be found.',
);

echo "All tests passed.\n";
