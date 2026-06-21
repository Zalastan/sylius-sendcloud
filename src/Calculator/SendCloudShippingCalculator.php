<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Calculator;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use SpiderWeb\Sylius\SendCloudPlugin\Api\SendCloudClient;
use Sylius\Component\Core\Model\ShipmentInterface as CoreShipmentInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class SendCloudShippingCalculator implements CalculatorInterface
{
    public const TYPE = 'sendcloud';

    /** @var array<string, int> In-memory cache per request to avoid redundant API calls */
    private array $localCache = [];

    public function __construct(
        private readonly SendCloudClient $client,
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /** @param array<string, mixed> $configuration */
    public function calculate(ShipmentInterface $subject, array $configuration): int
    {
        if (!$subject instanceof CoreShipmentInterface) {
            return 0;
        }

        $order = $subject->getOrder();
        $address = $order?->getShippingAddress();

        if ($address === null) {
            return 0;
        }

        $publicKey = $configuration['public_key'] ?? '';
        $privateKey = $configuration['private_key'] ?? '';
        $fromCountry = $configuration['from_country_code'] ?? '';
        $fromPostal = $configuration['from_postal_code'] ?? '';
        $overrideMode = (bool) ($configuration['enable_checkout_override'] ?? false);

        if ($publicKey === '' || $privateKey === '') {
            return 0;
        }

        if ($overrideMode) {
            return $this->calculateFromSelectedOption(
                $subject, $publicKey, $privateKey, $fromCountry, $fromPostal, $address,
            );
        }

        $optionCode = $configuration['shipping_option_code'] ?? '';
        if ($optionCode === '') {
            return 0;
        }

        return $this->fetchPrice(
            $publicKey, $privateKey, $optionCode,
            $fromCountry, $fromPostal,
            (string) $address->getCountryCode(), (string) $address->getPostcode(),
            $this->resolveWeightInKg($subject),
        );
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    private function calculateFromSelectedOption(
        CoreShipmentInterface $subject,
        string $publicKey,
        string $privateKey,
        string $fromCountry,
        string $fromPostal,
        mixed $address,
    ): int {
        $orderToken = $subject->getOrder()?->getTokenValue();
        if ($orderToken === null) {
            return 0;
        }

        $cacheItem = $this->cache->getItem('sendcloud_option_' . $orderToken);
        if (!$cacheItem->isHit()) {
            return 0;
        }

        $cached = $cacheItem->get();

        // New format: ['code' => '...', 'price_cents' => 450]
        if (is_array($cached)) {
            $optionCode = (string) ($cached['code'] ?? '');
            if ($optionCode === '') {
                return 0;
            }
            // Use cached price to avoid redundant API call
            if (isset($cached['price_cents'])) {
                return (int) $cached['price_cents'];
            }
        } else {
            // Legacy format: plain string option code
            $optionCode = (string) $cached;
        }

        if ($optionCode === '') {
            return 0;
        }

        return $this->fetchPrice(
            $publicKey, $privateKey, $optionCode,
            $fromCountry, $fromPostal,
            (string) $address->getCountryCode(), (string) $address->getPostcode(),
            $this->resolveWeightInKg($subject),
        );
    }

    private function fetchPrice(
        string $publicKey,
        string $privateKey,
        string $optionCode,
        string $fromCountry,
        string $fromPostal,
        string $toCountry,
        string $toPostal,
        float $weight,
    ): int {
        $cacheKey = implode('|', [$optionCode, $fromCountry, $fromPostal, $toCountry, $toPostal, $weight]);

        if (isset($this->localCache[$cacheKey])) {
            return $this->localCache[$cacheKey];
        }

        try {
            $price = $this->client->getShippingOptionPrice(
                publicKey: $publicKey,
                privateKey: $privateKey,
                shippingOptionCode: $optionCode,
                fromCountryCode: $fromCountry,
                fromPostalCode: $fromPostal,
                toCountryCode: $toCountry,
                toPostalCode: $toPostal,
                weightKg: $weight,
            );

            $this->localCache[$cacheKey] = $price;

            return $price;
        } catch (\Throwable $e) {
            $this->logger->error('SendCloud rate fetch failed for option {option}: {message}', [
                'option' => $optionCode,
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function resolveWeightInKg(CoreShipmentInterface $shipment): float
    {
        $weight = 0.0;
        foreach ($shipment->getUnits() as $unit) {
            $weight += (float) $unit->getOrderItem()->getVariant()?->getWeight();
        }

        return $weight > 0 ? $weight : 0.5;
    }
}
