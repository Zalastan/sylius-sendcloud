<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Calculator;

use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class SendCloudShippingCalculator implements CalculatorInterface
{
    public const TYPE = 'sendcloud';

    /** @param array<string, mixed> $configuration */
    public function calculate(ShipmentInterface $subject, array $configuration): int
    {
        // SendCloud handles carrier selection and label generation after checkout.
        // The shipping cost displayed at checkout is a flat amount defined per method.
        return (int) ($configuration['amount'] ?? 0);
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
