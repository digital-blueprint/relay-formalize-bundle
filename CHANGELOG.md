# Changelog

# Unreleased

# v0.4.16

* Add API tests
* Form: Replace JSON encoded string 'dataFeedSchema' by direct JSON object 'dataSchema'
* Deprecate 'dataFeedSchema'
* Submission: Replace JSON encoded string 'dataFeedElement' by direct JSON object 'data'
* Deprecate 'dataFeedElement'
* Replace yaml resource config by ApiResource annotations
* Allow empty submissions

# v0.4.15

* Re-allow application/json accept header for POST submissions for legacy system

# v0.4.14

* Add support for newer doctrine dbal/orm

# v0.4.13

* Re-allow application/json content-type for POST submissions for legacy system

# v0.4.12

* Update core (new ApiError)

# v0.4.11

* Drop support for Symfony v5
* Drop support for api-platform v2
* Add support for justinrainbow/json-schema v6 in addition to v5

# v0.4.10

* Update core and adapt function signatures

# v0.4.9

* guess and set form schema on first form submission (if not yet set), dropping validation of submissions by comparing the
data feed element keys with those of prior submission
* add basic output validation support to GET submission collection operations (only return submissions whose data feed element (JSON)
keys comply to those of the form schema)

# v0.4.8

* return granted actions for Form resources
* cache granted actions for one request

# v0.4.7

* Update authorization to v0.2

# v0.4.6

* Update authorization

# v0.4.5

* Remove parameter 'getAll' and implement the following get submission collection behaviour: The operation returns all 
submissions that the current user is authorized to read (all submissions of forms where they have a 'read_submissions' grant for and
single submissions that they are authorized to read, e.g. that they have posted). The parameter 'formIdentifier' is now optional and
can be considered as filter to list of submissions, returning only the submissions of the specified form that current user is
authorized to read (NOTE: it does neither throw 404 'not found' nor 403 'forbidden')

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
