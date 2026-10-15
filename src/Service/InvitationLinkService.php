<?php

namespace App\Service;

use App\Entity\Invitation;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class InvitationLinkService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UriSigner $uriSigner,
    ) {
    }

    public function create(Invitation $invitation, string $token): string
    {
        $url = $this->urlGenerator->generate('app_invitation_accept', [
            'id' => $invitation->getId(),
            'email' => $invitation->getEmail(),
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url, $invitation->getExpiresAt());
    }
}
