# Sumire

PHP 8.2 以降で動く、PDO ベースの小さな mapper です。実行時依存は `ext-pdo` のみです。

## 機能

- PHP Attributes による entity 定義
- PDO を使った `insert` / `update` / `delete` / `find`
- Criteria 配列による `findBy`
- Repository API
- トランザクションヘルパー
- SQLite / MySQL / PostgreSQL の識別子 quote
- PostgreSQL の generated id 取得は `INSERT ... RETURNING` を使用
- PDO の型付き parameter binding

## 対応データベース

Sumire は PDO の上に薄く乗る設計です。利用するデータベースに応じて、PHP 側に PDO ドライバを入れてください。

- SQLite: `pdo_sqlite`
- MySQL: `pdo_mysql`
- PostgreSQL: `pdo_pgsql`

採番 ID は SQLite/MySQL では `PDO::lastInsertId()`、PostgreSQL では `RETURNING` で取得します。

## 例

```php
use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;
use Sumire\Connection;
use Sumire\EntityManager;

#[Table('users')]
final class User
{
    #[Id]
    private ?int $id = null;

    #[Column]
    private string $name;

    #[Column]
    private string $email;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }
}

$pdo = new PDO('sqlite::memory:');
$orm = new EntityManager(new Connection($pdo));

$user = new User('Ada Lovelace', 'ada@example.com');
$orm->persist($user);

$found = $orm->repository(User::class)->find(1);
```

## 開発

```bash
composer dump-autoload
composer lint
composer test
```

MySQL / PostgreSQL の smoke test は DSN を渡して実行できます。

```bash
SUMIRE_DRIVER=mysql \
SUMIRE_DSN='mysql:host=127.0.0.1;port=3306;dbname=sumire_test;charset=utf8mb4' \
SUMIRE_USER=root \
SUMIRE_PASSWORD=secret \
composer test:integration

SUMIRE_DRIVER=pgsql \
SUMIRE_DSN='pgsql:host=127.0.0.1;port=5432;dbname=sumire_test' \
SUMIRE_USER=postgres \
SUMIRE_PASSWORD=secret \
composer test:integration
```
