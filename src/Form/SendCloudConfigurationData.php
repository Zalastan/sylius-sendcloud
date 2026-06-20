<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Form;

final class SendCloudConfigurationData
{
    public string $publicKey = '';

    public string $privateKey = '';

    public bool $enabled = true;
}
