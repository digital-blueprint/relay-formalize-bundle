# DbpRelayFormalizeBundle

[GitHub](https://github.com/digital-blueprint/relay-formalize-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-formalize-bundle) |
[Frontend Application](https://gitlab.tugraz.at/dbp/formalize/formalize)

[![Test](https://github.com/digital-blueprint/relay-formalize-bundle/actions/workflows/test.yml/badge.svg)](https://github.com/digital-blueprint/relay-formalize-bundle/actions/workflows/test.yml)

There is a corresponding frontend application that uses this API at [Formalize Frontend Application](https://gitlab.tugraz.at/dbp/formalize/formalize).

## Configuration

The bundle has a `database_url` configuration value that you can specify in your
app, either by hardcoding it, or by referencing an environment variable.

For this create `config/packages/dbp_relay_formalize.yaml` in the app with the following
content:

```yaml
dbp_relay_formalize:
  database_url: 'mysql://db:secret@mariadb:3306/db?serverVersion=mariadb-10.3.30'
  # database_url: %env(FORMALIZE_DATABASE_URL)%
```

If you were using the [DBP API Server Template](https://gitlab.tugraz.at/dbp/relay/dbp-relay-server-template)
as template for your Symfony application, then the configuration file should have already been generated for you.

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies from dbp/relay-formalize-bundle
composer update dbp/relay-formalize-bundle
```

## Scripts

### Database migration

Run this script to migrate the database. Run this script after installation of the bundle and
after every update to adapt the database to the new source code.

```bash
php bin/console doctrine:migrations:migrate --em=dbp_relay_formalize_bundle
```

## Error codes

### `/formalize/submissions`

#### POST

| relay:errorId                       | Status code | Description                                     | relay:errorDetails | Example                          |
|-------------------------------------|-------------|-------------------------------------------------| ------------------ |----------------------------------|
| `formalize:submission-not-created`  | 500         | The submission could not be created.            | `message`          | `['message' => 'Error message']` |
| `formalize:submission-invalid-json` | 422         | The dataFeedElement doesn't contain valid json. | `message`          |                                  |

### `/formalize/submissions/{identifier}`

#### GET

| relay:errorId                    | Status code | Description               | relay:errorDetails | Example |
| -------------------------------- | ----------- | ------------------------- | ------------------ | ------- |
| `formalize:submission-not-found` | 404         | Submission was not found. |                    |         |

## Roles

This bundle needs the role `ROLE_SCOPE_FORMALIZE` assigned to the user to get permissions to fetch data.
To create a new submission entry the Symfony role `ROLE_SCOPE_FORMALIZE-POST` is required.

## Events

To extend the behavior of the bundle the following event is registered:

### CreateSubmissionPostEvent

This event allows you to react on submission creations.
You can use this for example to email the submitter of the submission.

An event subscriber receives a `Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent` instance
in a service for example in `src/EventSubscriber/CreateSubmissionSubscriber.php`:

```php
<?php

namespace App\EventSubscriber;

use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateSubmissionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CreateSubmissionPostEvent::class => 'onPost',
        ];
    }

    public function onPost(CreateSubmissionPostEvent $event)
    {
        $submission = $event->getSubmission();
        $dataFeedElement = $submission->getDataFeedElementDecoded();

        // TODO: extract email address and send email
        $email = $dataFeedElement['email'];
    }
}
```
