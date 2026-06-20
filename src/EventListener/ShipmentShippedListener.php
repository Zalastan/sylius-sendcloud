<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\EventListener;

use Psr\Log\LoggerInterface;
use SpiderWeb\Sylius\SendCloudPlugin\Api\SendCloudClient;
use SpiderWeb\Sylius\SendCloudPlugin\Calculator\SendCloudShippingCalculator;
use Sylius\Component\Core\Model\ShipmentInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;

final class ShipmentShippedListener
{
    public function __construct(
        private readonly SendCloudClient $client,
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

        try {
            $parcel = $this->client->createParcel($publicKey, $privateKey, [
                'name' => trim($address->getFirstName() . ' ' . $address->getLastName()),
                'address' => $address->getStreet(),
                'city' => $address->getCity(),
                'postal_code' => $address->getPostcode(),
                'country' => ['iso_2' => $address->getCountryCode()],
                'telephone' => $address->getPhoneNumber() ?? '',
                'email' => $order->getCustomer()?->getEmail() ?? '',
                'order_number' => (string) $order->getNumber(),
                'weight' => $this->resolveWeightInKg($shipment),
                'request_label' => true,
            ]);

            if (isset($parcel['tracking_number'])) {
                $shipment->setTracking($parcel['tracking_number']);
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
