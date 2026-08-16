<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CaseNeedsConfirmationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $existingCase,
    ) {
        parent::__construct($message);
    }

    public function existingCase(): array
    {
        return $this->existingCase;
    }
}
