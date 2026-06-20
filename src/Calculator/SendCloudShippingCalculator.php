<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Calculator;

use Psr\Log\LoggerInterface;
use SpiderWeb\Sylius\SendCloudPlugin\Api\SendCloudClient;
use Sylius\Component\Core\Model\ShipmentInterface as CoreShipmentInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class SendCloudShippingCalculator implements CalculatorInterface
{
    public const TYPE = 'sendcloud';

    /** @var array<string, int> In-memory cache per request to avoid redundant API calls */
    private array $cache = [];

    public function __construct(
        private readonly SendCloudClient $client,
        private readonly LoggerInterface $logger,
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
        $optionCode = $configuration['shipping_option_code'] ?? '';
        $fromCountry = $configuration['from_country_code'] ?? '';
        $fromPostal = $configuration['from_postal_code'] ?? '';

        if ($publicKey === '' || $privateKey === '' || $optionCode === '') {
            return 0;
        }

        $toCountry = (string) $address->getCountryCode();
        $toPostal = (string) $address->getPostcode();
        $weight = $this->resolveWeightInKg($subject);

        $cacheKey = implode('|', [$optionCode, $fromCountry, $fromPostal, $toCountry, $toPostal, $weight]);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
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

            $this->cache[$cacheKey] = $price;

            return $price;
        } catch (\Throwable $e) {
            $this->logger->error('SendCloud rate fetch failed for option {option}: {message}', [
                'option' => $optionCode,
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function getType(): string
    {
        return self::TYPE;
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
