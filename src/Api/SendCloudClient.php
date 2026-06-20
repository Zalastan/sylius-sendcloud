<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Api;

use SpiderWeb\Sylius\SendCloudPlugin\Encryption\CredentialEncryptor;
use SpiderWeb\Sylius\SendCloudPlugin\Repository\SendCloudConfigurationRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendCloudClient
{
    private const BASE_URL = 'https://panel.sendcloud.sc/api/v2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CredentialEncryptor $encryptor,
        private readonly SendCloudConfigurationRepository $configRepository,
    ) {}

    /**
     * @param array<string, mixed> $parcelData
     * @return array<string, mixed>
     */
    public function createParcel(array $parcelData): array
    {
        $response = $this->request('POST', '/parcels', ['parcel' => $parcelData]);

        return $response['parcel'] ?? $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParcel(int $parcelId): array
    {
        return $this->request('GET', "/parcels/{$parcelId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getShippingMethods(): array
    {
        return $this->request('GET', '/shipping_methods');
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $config = $this->configRepository->findConfiguration();

        if ($config === null || !$config->isEnabled()) {
            throw new \RuntimeException('SendCloud is not configured or disabled.');
        }

        $publicKey = $this->encryptor->decrypt($config->getPublicKey());
        $privateKey = $this->encryptor->decrypt($config->getPrivateKey());

        $options = [
            'auth_basic' => [$publicKey, $privateKey],
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 10,
        ];

        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = $this->httpClient->request($method, self::BASE_URL . $path, $options);

        return $response->toArray();
    }
}
