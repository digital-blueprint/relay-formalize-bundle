# Setup

## Integration into the Relay API Server

* Add the bundle to your `config/bundles.php` before `DbpRelayCoreBundle`:

```php
...
Dbp\Relay\FormalizeBundle\DbpRelayFormalizeBundle::class => ['all' => true],
Dbp\Relay\CoreBundle\DbpRelayCoreBundle::class => ['all' => true],
];
```

If you were using the [DBP API Server Template](https://packagist.org/packages/dbp/relay-server-template)
as template for your Symfony application, then this should have already been generated for you.

* Run `composer install` to clear caches

## Database migration

Run this script to migrate the database. Run this script after installation of the bundle and
after every update to adapt the database to the new source code.

```bash
php bin/console doctrine:migrations:migrate --em=dbp_relay_formalize_bundle
```

## Demo form creation

To create a demo form and grant access to it, run the following command.

```bash
php bin/console dbp:relay:formalize:demo-form
```

If there is an error, delete the demo form from `formalize_forms` and try again.
