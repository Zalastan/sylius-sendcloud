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
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SendCloudPlugin' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__) . '/Entity',
                        'prefix' => 'SpiderWeb\Sylius\SendCloudPlugin\Entity',
                        'alias' => 'SendCloudPlugin',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'send_cloud';
    }
}
