<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Api;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendCloudClient
{
    private const BASE_URL = 'https://panel.sendcloud.sc/api/v3';

    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Returns the shipping price in cents for the given option and addresses.
     * Calls POST /api/v3/shipping-options with calculate_quotes=true.
     *
     * @throws \RuntimeException on API error or option not found
     */
    public function getShippingOptionPrice(
        string $publicKey,
        string $privateKey,
        string $shippingOptionCode,
        string $fromCountryCode,
        string $fromPostalCode,
        string $toCountryCode,
        string $toPostalCode,
        float $weightKg,
    ): int {
        $response = $this->request($publicKey, $privateKey, 'POST', '/shipping-options', [
            'from_address' => [
                'country_code' => $fromCountryCode,
                'postal_code' => $fromPostalCode,
            ],
            'to_address' => [
                'country_code' => $toCountryCode,
                'postal_code' => $toPostalCode,
            ],
            'parcels' => [
                ['weight' => ['value' => (string) round($weightKg, 3), 'unit' => 'kg']],
            ],
            'shipping_option_code' => $shippingOptionCode,
            'calculate_quotes' => true,
        ]);

        $option = $response['data'][0] ?? null;
        if ($option === null) {
            throw new \RuntimeException(sprintf('SendCloud returned no shipping option for code "%s".', $shippingOptionCode));
        }

        $priceValue = $option['quotes'][0]['price']['total']['value'] ?? null;
        if ($priceValue === null) {
            throw new \RuntimeException(sprintf('SendCloud returned no price quote for option "%s".', $shippingOptionCode));
        }

        return (int) round((float) $priceValue * 100);
    }

    /**
     * Returns all delivery options configured in SendCloud for the given route.
     * Calls POST /api/v3/checkout/delivery-options.
     *
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException on API error
     */
    public function getDeliveryOptions(
        string $publicKey,
        string $privateKey,
        string $fromCountryCode,
        string $fromPostalCode,
        string $toCountryCode,
        string $toPostalCode,
        float $weightKg,
        int $totalPriceCents = 0,
    ): array {
        $response = $this->request($publicKey, $privateKey, 'POST', '/checkout/delivery-options', [
            'from_address' => ['country_code' => $fromCountryCode, 'postal_code' => $fromPostalCode],
            'to_address' => ['country_code' => $toCountryCode, 'postal_code' => $toPostalCode],
            'total_weight' => ['value' => number_format($weightKg, 3, '.', ''), 'unit' => 'kg'],
            'total_price' => ['value' => number_format($totalPriceCents / 100, 2, '.', ''), 'currency' => 'EUR'],
        ]);

        return $response['delivery_options'] ?? [];
    }

    /**
     * Creates a parcel and requests a shipping label.
     *
     * @param array<string, mixed> $parcelData
     * @return array<string, mixed>
     */
    public function createParcel(string $publicKey, string $privateKey, array $parcelData): array
    {
        $response = $this->request($publicKey, $privateKey, 'POST', '/shipments', [
            'shipments' => [$parcelData],
        ]);

        return $response['data'][0] ?? $response;
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
            'timeout' => 5,
        ];

        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = $this->httpClient->request($method, self::BASE_URL . $path, $options);

        return $response->toArray();
    }
}
