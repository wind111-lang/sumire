# Development

## Install Dependencies

```bash
composer update
```

## Local Checks

```bash
composer validate --strict
composer lint
composer test
composer analyse
composer cs:check
```

Run the full local CI command:

```bash
composer ci
```

## Format Code

```bash
composer cs:fix
```

## Static Analysis

PHPStan runs at level 6.

```bash
composer analyse
```

## Unit Tests

```bash
composer test
```

## Integration Smoke Tests

Integration smoke tests require a real MySQL or PostgreSQL database.

### MySQL

```bash
SUMIRE_DRIVER=mysql \
SUMIRE_DSN='mysql:host=127.0.0.1;port=3306;dbname=sumire_test;charset=utf8mb4' \
SUMIRE_USER=root \
SUMIRE_PASSWORD=secret \
composer test:integration
```

### PostgreSQL

```bash
SUMIRE_DRIVER=pgsql \
SUMIRE_DSN='pgsql:host=127.0.0.1;port=5432;dbname=sumire_test' \
SUMIRE_USER=postgres \
SUMIRE_PASSWORD=secret \
composer test:integration
```

## Pull Request Workflow

Use pull requests for repository changes.

1. Create a branch from `main`.
2. Make the change.
3. Run the relevant checks.
4. Open a draft pull request.
5. Watch GitHub Actions.
6. Mark the pull request ready when the change is ready for review.

Pull request descriptions should include:

- Description
- What Changed
- Affect
- Tests
