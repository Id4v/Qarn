<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
final readonly class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->findOneBy(['email' => strtolower(trim($identifier))]);
        if (!$user instanceof User) {
            $exception = new UserNotFoundException('User not found.');
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User || null === $user->getId()) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $refreshedUser = $this->users->find($user->getId());
        if (!$refreshedUser) {
            $exception = new UserNotFoundException('User not found.');
            $exception->setUserIdentifier($user->getUserIdentifier());

            throw $exception;
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $this->users->upgradePassword($user, $newHashedPassword);
    }
}
