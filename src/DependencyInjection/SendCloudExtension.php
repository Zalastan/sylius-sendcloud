<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class SendCloudExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('sylius_twig_hooks')) {
            $container->prependExtensionConfig('sylius_twig_hooks', [
                'hooks' => [
                    'sylius_shop.checkout.select_shipping.content.form' => [
                        'sendcloud_checkout_options' => [
                            'template' => '@SendCloudPlugin/Checkout/_sendcloud_options.html.twig',
                            'priority' => 50,
                        ],
                    ],
                ],
            ]);
        }
    }

    public function getAlias(): string
    {
        return 'send_cloud';
    }
}
