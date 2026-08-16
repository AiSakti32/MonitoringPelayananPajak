<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function from(): int
    {
        if ($this->total === 0) {
            return 0;
        }
        return (($this->page - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        if ($this->total === 0) {
            return 0;
        }
        return min($this->total, $this->page * $this->perPage);
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public static function normalizePage(mixed $page): int
    {
        $p = (int) $page;
        return $p < 1 ? 1 : $p;
    }

    public static function offset(int $page, int $perPage): int
    {
        return (max(1, $page) - 1) * $perPage;
    }
}
