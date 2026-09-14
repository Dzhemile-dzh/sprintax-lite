<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $id): User
    {
        $user = $this->entityManager->find(User::class, $id);

        if (!$user instanceof User) {
            throw UserNotFound::withId($id);
        }

        return $user;
    }

    public function findByEmail(Email $email): ?User
    {
        $user = $this->entityManager->createQueryBuilder()
            ->select('user')
            ->from(User::class, 'user')
            ->where('user.email = :email')
            ->setParameter('email', $email->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $user instanceof User ? $user : null;
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
