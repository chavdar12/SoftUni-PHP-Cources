<?php

namespace App\Service;


use App\Data\UserDTO;
use App\Repository\UserRepositoryInterface;
use App\Service\Encryption\EncryptionServiceInterface;

class UserService implements UserServiceInterface
{
    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @var EncryptionServiceInterface
     */
    private $encryptionService;

    public function __construct(UserRepositoryInterface    $userRepository,
                                EncryptionServiceInterface $encryptionService)
    {
        $this->userRepository = $userRepository;
        $this->encryptionService = $encryptionService;
    }

    public function register(UserDTO $userDTO, string $confirmPassword): bool
    {
        if ($userDTO->getPassword() !== $confirmPassword) {
            return false;
        }

        if (null !== $this->userRepository->findOneByUsername($userDTO->getUsername())) {
            return false;
        }

        $this->encryptPassword($userDTO);
        return $this->userRepository->insert($userDTO);
    }

    /**
     * @param UserDTO $userDTO
     */
    private function encryptPassword(UserDTO $userDTO): void
    {
        $plainPassword = $userDTO->getPassword();
        $passwordHash = $this->encryptionService->hash($plainPassword);
        $userDTO->setPassword($passwordHash);
    }

    public function login(string $username, string $password): ?UserDTO
    {
        $userFromDB = $this->userRepository->findOneByUsername($username);

        if (null === $userFromDB) {
            return null;
        }

        if (false === $this->encryptionService->verify($password, $userFromDB->getPassword())) {
            return null;
        }

        return $userFromDB;
    }

    public function isLogged(): bool
    {
        if (!$this->currentUser()) {
            return false;
        }
        return true;
    }

    public function currentUser(): ?UserDTO
    {
        if (!$_SESSION['id']) {
            return null;
        }

        return $this->userRepository->findOneById(intval($_SESSION['id']));
    }

    public function edit(UserDTO $userDTO): bool
    {
        /** @var $currentUser UserDTO */
        $currentUser = $this->currentUser();
        $currentUserUserNameChanged = false;
        if (null !== $currentUser) {
            $currentUser->getUsername() !== $userDTO->getUsername() ? $currentUserUserNameChanged = true : $currentUserUserNameChanged = false;
        } else {
            return false;
        }

        if ($currentUserUserNameChanged && (null !== $this->userRepository->findOneByUsername($userDTO->getUsername()))) {
            return false;
        }

        $this->encryptPassword($userDTO);
        return $this->userRepository->update(intval($_SESSION['id']), $userDTO);
    }

    /**
     * @return \Generator|UserDTO[]
     */
    public function getAll(): \Generator
    {
        return $this->userRepository->findAll();
    }
}