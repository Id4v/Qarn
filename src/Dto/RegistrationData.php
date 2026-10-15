<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $displayName = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    public string $plainPassword = '';
}
