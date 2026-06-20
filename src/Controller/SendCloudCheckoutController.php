<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use SpiderWeb\Sylius\SendCloudPlugin\Api\SendCloudClient;
use SpiderWeb\Sylius\SendCloudPlugin\Calculator\SendCloudShippingCalculator;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Shipping\Repository\ShippingMethodRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SendCloudCheckoutController
{
    public function __construct(
        private readonly SendCloudClient $client,
        private readonly CartContextInterface $cartContext,
        private readonly ShippingMethodRepositoryInterface $shippingMethodRepository,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function deliveryOptions(Request $request): JsonResponse
    {
        $methodId = (int) $request->query->get('method_id', 0);
        if ($methodId === 0) {
            return new JsonResponse(['error' => 'Missing method_id'], 400);
        }

        $method = $this->shippingMethodRepository->find($methodId);
        if ($method === null || $method->getCalculator() !== SendCloudShippingCalculator::TYPE) {
            return new JsonResponse(['error' => 'Invalid shipping method'], 400);
        }

        $config = $method->getConfiguration();
        if (!($config['enable_checkout_override'] ?? false)) {
            return new JsonResponse(['error' => 'Dynamic checkout not enabled for this method'], 400);
        }

        try {
            /** @var OrderInterface $order */
            $order = $this->cartContext->getCart();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'No active cart'], 400);
        }

        $address = $order->getShippingAddress();
        if ($address === null) {
            return new JsonResponse(['error' => 'No shipping address on order'], 400);
        }

        $weightKg = 0.0;
        foreach ($order->getShipments() as $shipment) {
            foreach ($shipment->getUnits() as $unit) {
                $weightKg += (float) $unit->getOrderItem()->getVariant()?->getWeight();
            }
        }
        if ($weightKg <= 0.0) {
            $weightKg = 0.5;
        }

        try {
            $options = $this->client->getDeliveryOptions(
                publicKey: $config['public_key'],
                privateKey: $config['private_key'],
                fromCountryCode: $config['from_country_code'],
                fromPostalCode: $config['from_postal_code'],
                toCountryCode: (string) $address->getCountryCode(),
                toPostalCode: (string) $address->getPostcode(),
                weightKg: $weightKg,
                totalPriceCents: $order->getItemsTotal(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('SendCloud delivery options fetch failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'Failed to fetch SendCloud options'], 500);
        }

        return new JsonResponse([
            'options' => $options,
            'order_token' => $order->getTokenValue(),
        ]);
    }

    public function selectOption(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $optionCode = $data['option_code'] ?? null;
        $orderToken = $data['order_token'] ?? null;

        if (!is_string($optionCode) || $optionCode === '') {
            return new JsonResponse(['error' => 'Missing option_code'], 400);
        }
        if (!is_string($orderToken) || $orderToken === '') {
            return new JsonResponse(['error' => 'Missing order_token'], 400);
        }

        $item = $this->cache->getItem('sendcloud_option_' . $orderToken);
        $item->set($optionCode);
        $item->expiresAfter(7 * 24 * 3600);
        $this->cache->save($item);

        return new JsonResponse(['success' => true]);
    }
}
