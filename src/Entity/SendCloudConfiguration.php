<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sendcloud_configuration')]
class SendCloudConfiguration
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    private ?int $id = null;

    #[ORM\Column(type: 'text', name: 'public_key')]
    private string $publicKey = '';

    #[ORM\Column(type: 'text', name: 'private_key')]
    private string $privateKey = '';

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
