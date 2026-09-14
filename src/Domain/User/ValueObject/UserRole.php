<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

enum UserRole: string
{
    case Admin = 'ROLE_ADMIN';
    case Client = 'ROLE_CLIENT';
}
