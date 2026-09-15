<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DTO;

final readonly class StatusCount
{
    public function __construct(
        public string $status,
        public int $count,
    ) {
    }
}
