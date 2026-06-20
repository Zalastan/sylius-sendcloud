<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Api;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendCloudClient
{
    private const BASE_URL = 'https://panel.sendcloud.sc/api/v2';

    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * @param array<string, mixed> $parcelData
     * @param array<string, mixed>
     */
    public function createParcel(string $publicKey, string $privateKey, array $parcelData): array
    {
        $response = $this->request($publicKey, $privateKey, 'POST', '/parcels', ['parcel' => $parcelData]);

        return $response['parcel'] ?? $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getShippingMethods(string $publicKey, string $privateKey): array
    {
        return $this->request($publicKey, $privateKey, 'GET', '/shipping_methods');
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $publicKey, string $privateKey, string $method, string $path, ?array $body = null): array
    {
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
