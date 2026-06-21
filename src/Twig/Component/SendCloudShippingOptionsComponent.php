<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Twig\Component;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use SpiderWeb\Sylius\SendCloudPlugin\Api\SendCloudClient;
use SpiderWeb\Sylius\SendCloudPlugin\Calculator\SendCloudShippingCalculator;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Shipping\Repository\ShippingMethodRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'sendcloud_shipping_options',
    template: '@SendCloudPlugin/components/SendCloudShippingOptions.html.twig',
)]
final class SendCloudShippingOptionsComponent
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    public const EVENT_OPTION_SELECTED = 'sendcloud:option:selected';

    #[LiveProp]
    public int $methodId = 0;

    #[LiveProp(writable: true)]
    public string $selectedOptionCode = '';

    /** @var list<array<string, mixed>>|null */
    private ?array $resolvedOptions = null;

    private bool $fetchFailed = false;

    public function __construct(
        private readonly SendCloudClient $client,
        private readonly CartContextInterface $cartContext,
        private readonly ShippingMethodRepositoryInterface $shippingMethodRepository,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return list<array<string, mixed>> */
    public function getOptions(): array
    {
        if ($this->resolvedOptions !== null) {
            return $this->resolvedOptions;
        }

        $method = $this->shippingMethodRepository->find($this->methodId);
        if ($method === null || $method->getCalculator() !== SendCloudShippingCalculator::TYPE) {
            return $this->resolvedOptions = [];
        }

        $config = $method->getConfiguration();
        $publicKey = (string) ($config['public_key'] ?? '');
        $privateKey = (string) ($config['private_key'] ?? '');

        if ($publicKey === '' || $privateKey === '') {
            $this->logger->warning('SendCloud: API credentials missing for shipping method {id}', ['id' => $this->methodId]);
            $this->fetchFailed = true;
            return $this->resolvedOptions = [];
        }

        try {
            /** @var OrderInterface $order */
            $order = $this->cartContext->getCart();
        } catch (\Throwable) {
            return $this->resolvedOptions = [];
        }

        $address = $order->getShippingAddress();
        if ($address === null) {
            return $this->resolvedOptions = [];
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
            $this->resolvedOptions = $this->client->getDeliveryOptions(
                publicKey: $publicKey,
                privateKey: $privateKey,
                fromCountryCode: $config['from_country_code'],
                fromPostalCode: $config['from_postal_code'],
                toCountryCode: (string) $address->getCountryCode(),
                toPostalCode: (string) $address->getPostcode(),
                weightKg: $weightKg,
                totalPriceCents: $order->getItemsTotal(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('SendCloud: failed to fetch delivery options: {message}', [
                'message' => $e->getMessage(),
            ]);
            $this->fetchFailed = true;
            $this->resolvedOptions = [];
        }

        return $this->resolvedOptions;
    }

    public function hasFetchError(): bool
    {
        $this->getOptions();

        return $this->fetchFailed;
    }

    public function getOrderToken(): ?string
    {
        try {
            return $this->cartContext->getCart()->getTokenValue();
        } catch (\Throwable) {
            return null;
        }
    }

    #[LiveAction]
    public function select(#[LiveArg] string $optionCode, #[LiveArg] int $priceCents = 0): void
    {
        $orderToken = $this->getOrderToken();
        if ($orderToken === null || $optionCode === '') {
            return;
        }

        $item = $this->cache->getItem('sendcloud_option_' . $orderToken);
        $item->set(['code' => $optionCode, 'price_cents' => $priceCents]);
        $item->expiresAfter(7 * 24 * 3600);
        $this->cache->save($item);

        $this->selectedOptionCode = $optionCode;

        $this->emit(self::EVENT_OPTION_SELECTED, [
            'methodId' => $this->methodId,
            'optionCode' => $optionCode,
        ]);
    }
}
