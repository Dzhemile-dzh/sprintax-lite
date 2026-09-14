<?php

declare(strict_types=1);

namespace App\Application\User;

interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;
}
