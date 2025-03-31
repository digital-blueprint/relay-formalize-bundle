# DbpRelayFormalizeBundle

[GitHub](https://github.com/digital-blueprint/relay-formalize-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-formalize-bundle) |
[Frontend Application](https://gitlab.tugraz.at/dbp/formalize/formalize)

[![Test](https://github.com/digital-blueprint/relay-formalize-bundle/actions/workflows/test.yml/badge.svg)](https://github.com/digital-blueprint/relay-formalize-bundle/actions/workflows/test.yml)

Formalize is a powerful PHP 8.1+/Symfony **form and submission management tool** that covers a wide range of use cases from 
- simple event registrations to
- complex multi-stage submission workflows (draft-submission-review-approval)

It offers professional form and submission authorization based on the logged-in user's s attributes
(id, group membership, roles etc., see the [DbpRelayAuthorizationBundle](https://github.com/digital-blueprint/relay-authorization-bundle) for details).

**Features include**:
- Form schema, input/output validation
- Forms with file upload
- Submission drafts
- Collaborative viewing and editing of submissions
- Submission events (e.g. for sending confirmation e-mails)
- Submission exports

It integrates seamlessly with the [Relay API Server](https://packagist.org/packages/dbp/relay-server-template) and offers a corresponding [frontend application](https://github.com/digital-blueprint/formalize-app).

Please see the [documentation](./docs/README.md) for more information.

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-formalize-bundle).

```bash
composer require dbp/relay-formalize-bundle
```

To update the bundle and its dependencies:
```bash
composer update dbp/relay-formalize-bundle
```

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`
