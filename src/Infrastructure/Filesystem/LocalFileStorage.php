<?php

declare(strict_types=1);

namespace App\Infrastructure\Filesystem;

use App\Domain\Filesystem\Contract\FileStorageInterface;
use RuntimeException;

final class LocalFileStorage implements FileStorageInterface
{
    public function exists(string $path): bool
    {
        return is_file($path);
    }

    public function isReadable(string $path): bool
    {
        return is_file($path) && is_readable($path);
    }

    public function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create directory "%s".', $directory));
        }
    }
}
