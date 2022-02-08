# DbpRelayFormsBundle

This Symfony bundle can be used as a template for creating new bundles for the
DBP Relay project.

When including this bundle into your API server it will gain the following
features:

* A custom `./bin/console` command
* An example entity
* Various HTTP methods implemented for that entity

## TL;DR

The quickest way to make use of this template bundle is to feed your desired names
to one command and generate a ready-to-use bundle with the correct naming.

See [Generate DBP Symfony bundle](https://dbp-demo.tugraz.at/dev-guide/relay/naming/#generate-dbp-symfony-bundle) for more information.

## Using the Bundle as a Template

* Copy the repo contents
* Adjust the package name in `composer.json`, in this example we'll pretend you named your bundle `dbp/relay-your-bundle`
* Invent a new PHP namespace and adjust it in all PHP files
* Rename `src/DbpRelayFormsBundle` and `DependencyInjection/DbpRelayFormsExtension` to match the new project name

## Integration into the API Server

* Push your bundle on a git server, in this example we'll use `git@gitlab.tugraz.at:dbp/relay/dbp-relay-your-bundle.git`
* Add the repository to your composer.json (as soon as you published your bundle to [Packagist](https://packagist.org/)
  you can remove that block again):

```json
    "repositories": [
        {
            "type": "vcs",
            "url": "git@gitlab.tugraz.at:dbp/relay/dbp-relay-your-bundle.git"
        }
    ],
```

* Add the bundle package as a dependency:

```bash
composer require dbp/relay-your-bundle=dev-main
```

* Add the bundle to your `config/bundles.php`:

```php
...
Dbp\Relay\YourBundle\DbpRelayYourBundle::class => ['all' => true],
DBP\API\CoreBundle\DbpCoreBundle::class => ['all' => true],
];
```

* Run `composer install` to clear caches

## Configuration

The bundle has a `example_config` configuration value that you can specify in your
app, either by hard-coding it, or by referencing an environment variable.

For this create `config/packages/dbp_relay_forms.yaml` in the app with the following
content:

```yaml
dbp_relay_forms:
  example_config: 42
  # example_config: '%env(EXAMPLE_CONFIG)%'
```

The value gets read in `DbpRelayFormsExtension` (your extension will be named differently)
and passed when creating the `MyCustomService` service.

For more info on bundle configuration see [Symfony bundles configuration](https://symfony.com/doc/current/bundles/configuration.html).

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies from dbp/relay-your-bundle
composer update dbp/relay-your-bundle
```
