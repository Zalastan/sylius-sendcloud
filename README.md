# Sylius SendCloud Plugin

Sylius 2 plugin for SendCloud shipping automation. Automatically creates parcels in SendCloud when a shipment is marked as shipped, and stores the tracking number back on the shipment.

## Features

- Creates a SendCloud parcel automatically on the `ship` workflow transition
- Stores the tracking number returned by SendCloud on the Sylius shipment
- Encrypts API credentials at rest using libsodium (`ext-sodium`)
- Admin configuration page under `/admin/sendcloud/configuration`

## Requirements

- PHP 8.2+
- Sylius 2.0+
- `ext-sodium`

## Installation

```bash
composer require spiderweb/sylius-sendcloud-plugin
```

Register the plugin in your Symfony application:

```php
// config/bundles.php
return [
    // ...
    SpiderWeb\Sylius\SendCloudPlugin\SendCloudPlugin::class => ['all' => true],
];
```

Import the routes:

```yaml
# config/routes/sendcloud.yaml
spiderweb_sendcloud:
    resource: "@SendCloudPlugin/config/routes.yaml"
```

Run the database migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Configuration

Go to **Admin → SendCloud** and enter your SendCloud API credentials (Settings → Integrations → API in your SendCloud account).

API keys are encrypted in the database using your application's `APP_SECRET`. If `APP_SECRET` changes, you will need to re-enter the credentials.

## License

MIT — [Spider Web](https://www.spider-web.fr)
