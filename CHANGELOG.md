# Changelog

# Unleased

# v0.4.4

* Add submission level authorization as a new form attribute
* Enable cascade delete for form submissions on form deletion
* Add a new parameter 'getAll' to the GET submission collection operation. If specified, all form submissions are returned.
Otherwise, only the form submissions the logged-in user is granted to read are returned (requires submission level authorization
to be enabled in the form)

# v0.4.3

* Fix migration

# v0.4.0

* Replace user attribute based authorization by the resource-action-grant based authorization from the new 
dbp/relay-authorization-bundle

## v0.3.24

* Port to PHPUnit 10

## v0.3.23

* Port from doctrine annotations to php attributes

## v0.3.22

* Fix form patch response with api-platform 3.2

## v0.3.21

* Add support for api-platform 3.2

## v0.3.20

* Change Content-Type for PATCH operations to "application/merge-patch+json"

## v0.3.18

* Add support for Symfony 6

## v0.3.16

* Drop support for PHP 7.4/8.0

## v0.2.3

* Port to the new api-platform metadata system

## v0.2.2

* Update to api-platform v2.7
