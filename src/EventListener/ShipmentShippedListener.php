<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\EventListener;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use SpiderWeb\Sylius\SendCloudPlugin\Api\SendCloudClient;
use SpiderWeb\Sylius\SendCloudPlugin\Calculator\SendCloudShippingCalculator;
use Sylius\Component\Core\Model\ShipmentInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;

final class ShipmentShippedListener
{
    public function __construct(
        private readonly SendCloudClient $client,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TransitionEvent $event): void
    {
        $shipment = $event->getSubject();
        if (!$shipment instanceof ShipmentInterface) {
            return;
        }

        $method = $shipment->getMethod();
        if ($method === null || $method->getCalculator() !== SendCloudShippingCalculator::TYPE) {
            return;
        }

        $config = $method->getConfiguration();
        $publicKey = $config['public_key'] ?? null;
        $privateKey = $config['private_key'] ?? null;

        if ($publicKey === null || $privateKey === null) {
            return;
        }

        $order = $shipment->getOrder();
        if ($order === null) {
            return;
        }

        $address = $order->getShippingAddress();
        if ($address === null) {
            return;
        }

        $overrideMode = (bool) ($config['enable_checkout_override'] ?? false);

        if ($overrideMode) {
            $orderToken = $order->getTokenValue();
            $cacheItem = $orderToken !== null
                ? $this->cache->getItem('sendcloud_option_' . $orderToken)
                : null;
            $shippingOptionCode = ($cacheItem !== null && $cacheItem->isHit())
                ? (string) $cacheItem->get()
                : '';

            if ($shippingOptionCode === '') {
                $this->logger->warning('SendCloud: no selected delivery option found for order {order} in dynamic mode.', [
                    'order' => $order->getNumber(),
                ]);

                return;
            }
        } else {
            $shippingOptionCode = $config['shipping_option_code'] ?? '';
            if ($shippingOptionCode === '') {
                return;
            }
        }

        $fromCountryCode = $config['from_country_code'] ?? '';
        $fromPostalCode = $config['from_postal_code'] ?? '';

        try {
            $parcel = $this->client->createParcel($publicKey, $privateKey, [
                'shipping_option_code' => $shippingOptionCode,
                'to_address' => [
                    'name' => trim($address->getFirstName() . ' ' . $address->getLastName()),
                    'address_line_1' => $address->getStreet(),
                    'city' => $address->getCity(),
                    'postal_code' => $address->getPostcode(),
                    'country_code' => $address->getCountryCode(),
                    'phone_number' => $address->getPhoneNumber() ?? '',
                    'email' => $order->getCustomer()?->getEmail() ?? '',
                ],
                'from_address' => [
                    'country_code' => $fromCountryCode,
                    'postal_code' => $fromPostalCode,
                ],
                'parcels' => [[
                    'weight' => ['value' => $this->resolveWeightInKg($shipment), 'unit' => 'kg'],
                ]],
                'order_number' => (string) $order->getNumber(),
                'request_label' => true,
            ]);

            if (isset($parcel['tracking_number']) || isset($parcel['tracking'])) {
                $shipment->setTracking($parcel['tracking_number'] ?? $parcel['tracking']);
            }
        } catch (\Throwable $e) {
            $this->logger->error('SendCloud parcel creation failed for order {order}: {message}', [
                'order' => $order->getNumber(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function resolveWeightInKg(ShipmentInterface $shipment): string
    {
        $weight = 0.0;
        foreach ($shipment->getUnits() as $unit) {
            $weight += (float) $unit->getOrderItem()->getVariant()?->getWeight();
        }

        return number_format($weight > 0 ? $weight : 0.5, 3, '.', '');
    }
}
