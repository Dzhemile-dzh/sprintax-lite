<?php

declare(strict_types=1);

namespace App\Domain\Filesystem\Contract;

interface FileStorageInterface
{
    public function exists(string $path): bool;

    public function isReadable(string $path): bool;

    public function ensureDirectory(string $directory): void;
}
