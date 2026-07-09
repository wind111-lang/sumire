<?php

declare(strict_types=1);

namespace Sumire\Tests\Fixtures;

use Sumire\Attributes\Column;
use Sumire\Attributes\Id;
use Sumire\Attributes\Table;

#[Table('users')]
final class User
{
    #[Id(name: 'id')]
    private ?int $id = null;

    #[Column(name: 'name')]
    private string $name;

    #[Column(name: 'email')]
    private string $email;

    #[Column(name: 'active')]
    private bool $active;

    public function __construct(string $name, string $email, bool $active = true)
    {
        $this->name = $name;
        $this->email = $email;
        $this->active = $active;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function changeEmail(string $email): void
    {
        $this->email = $email;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
