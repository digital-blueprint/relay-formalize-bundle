# Changelog

## Unreleased

- Split form item (`DbpRelayFormalizeForm`) grants into form item (read, update, delete) and submission collection
(`DbpRelaySubmissionCollection`) grants (create grant, read/update/delete all submissions of the collection),
both using the form identifier as resource identifier.
- Add submissions to the form's submission collection on submission.
- Add allowed tag actions to submission `grantedActions`
- Update authorization bundle: provide available actions explicitly (on post DB migration) instead of by
event subscribers on each request

## v0.5.20

- Unpin core bundle

## v0.5.19

- Add migration for the forms' `availableTags`

## v0.5.18

- Add `tagPermissionsForSubmitters` to the form entity, i.e., the tag permissions that users with have submission-level
permissions (as opposed to form-level submission permissions, which are valid for all (non-draft) submissions of a form).
Possible values are:
  - TAG_PERMISSIONS_NONE (0): submitters have no tag permissions
  - TAG_PERMISSIONS_READ (1): submitters can read tags (default)
  - TAG_PERMISSIONS_ADD (2): submitters can read and add tags
  - TAG_PERMISSIONS_ADD_REMOVE (3): submitters can read, add and remove tags

## v0.5.17

- Make `Form::availableTags` an object which has to have at least a non-empty `identifier` property. Other attributes, like 
`backgroundColor` and `name` can be used by the frontend to customize the display of tags.
- Restrict visibility of `Form::availableTags` and `Submission::tags` to users with read permissions for all form submissions

## v0.5.16

- Add field `lastModifiedById` to the submission entity,
which is the user that last modified the submission (initially set to the creator on creation)
- Update relay-authorization-bundle
- Change grant-based submission authorization on-submission behavior:
    - On-submission, all grants for the submission are removed (manage grant by the creator and shared grants)
      and the submitter is granted the actions defined in `submission.form.allowedActionsWhenSubmitted` (this behavior will
      likely change in the future such that existing grants that are also found in `submission.form.allowedActionsWhenSubmitted`are preserved

## v0.5.15

- Minor phpstan fixes

## v0.5.14

- Fix merging of form-level and submission-level granted actions

## v0.5.13

- Add `tags` to submission entity and `availableTags` to form entity
- Remove submission state `ACCEPTED`

## v0.5.11

- Localize available form and submission actions
- Add `SubmissionGrantAddedEvent`
- Update `relay-authorization-bundle` to v0.5

## v0.5.10

- Change deletion of submitted files:
```
Content-Disposition: form-data; name="submittedFiles[<submitted file to delete UUID>]"

null
```
- Fix POST-only forms for the deprecated JSONLD create submission operation
- Allow setting the authorization type on form creation

## v0.5.9

- specify submission item actions in GetAvailableResourceClassActionsEventSubscriber
- remove POST/PATCH /formalize/submissions/multipart
- change PATCH /formalize/submissions/<UUID> to only accept multipart/form-data
- change POST /formalize/submissions to accept multipart/form-data (standard) and application/ld+json (for compliance with legacy systems)
- replace `submittedFilesToDelete` array parameter from submission PATCH by the following, more RESTy syntax:
```
Content-Disposition: form-data; name="<file input name>[<submitted file to delete UUID>]"

null
```

## v0.5.8

- add ''numSubmissionsByCurrentUser' to form entity (only available for item operations)

## v0.5.7

- add 'creatorIdEquals' filter parameter to GET submission collection operation

## v0.5.6

- replace justinrainbow/json-schema by opis/json-schema, since the latter supports conditional schema validation
(e.g. "dependentRequired", "if", "then", else")

## v0.5.5

- validate custom JSON (form) schema extensions `localizedName` and `tableViewVisibleDefault`

## v0.5.4

- introduce submission state `ACCEPTED`

## v0.5.3

- Update blob

## v0.5.2

- Cleanup blob test files (from storage)

## v0.5.1

- Update the blob library

## v0.5.0

- Remove relay-blob-bundle (FileApi) dependency and replace by relay-blob-library, which can be configured to access 
blob locally (via PHP) or remotely (via HTTP)

## v0.4.29

- Fix: Get own/shared drafts, when a user with form level read submissions requests form submissions

## v0.4.28

- Add support for api-platform v4.1

## v0.4.27

- prepare for upgrade to api-platform v4.1: replace ApiResource.openapiContext by ApiResource.openapi

## v0.4.26

- add `additionalFiles` to files section of form schema to allow posting file attributes not
defined in the form schema

## v0.4.25

- Support uploading files as part of submissions
  - Add POST and PATCH multipart endpoints
  - Add `SubmittedFile` entity
  - Store the submitted files with Blob
  - Extend the form schema by a `files` section
  - Auto-generate the `files` section of the form schema on first POST
- Add SubmissionSubmittedPostEvent, SubmittedSubmissionUpdatedPostEvent, deprecate CreateSubmissionPostEvent 
- Add form attribute: maximum number of form submissions per creator, which will cause a 403 forbidden error
when one creator tries to post more submissions to a form than are allowed for the form
- Add submission attribute date last modified, which is set to date created on creation and then set to the current 
time on every update

## v0.4.23

- Add filter `whereMayReadSubmissions` to GET form collection request, which limits the form results to 
those that the user either has read (all) form submissions permissions for, or has submissions that they may read
(e.g. their own submissions)

## v0.4.22

- Create a command that creates and sets up the Demo Form
- Add granted actions to Submission API output
- Add filters:
  - `whereReadFormSubmissionsGranted`: only return forms where the set of allowed actions contains `read_submissions`,
  i.e. the current user is allowed to read all submissions of
  - `whereContainsSubmissionsMayRead`: only return forms that contain at least one submission that the current
  user has a submission-level read permission for. NOTE: forms the current user has a `read_submissions` permission for
  are only returned if they contain at least one submission that the current user has created or was granted read access to.
- Add Form.allowedActionsWhenSubmitted defining the maximum set of allowed actions once the submission is in
 submitted state, regardless of the grants the user may have for the submission (NOTE: it limits the set of 
allowed actions and does not add to it)
- Rename Form.submissionLevelAuthorization to Form.grantBasedSubmissionAuthorization:
    - If true, authorization decisions are based on grants (managed by the authorization bundle).
      When new submissions are registered, the creator is issued a manage grant and may thus issue grants for the submission to other user.
    - If false (-> created-based submission authorization), authorization decisions or based on the creatorId of the submission.
- Add submissionState to the submission which allows to save drafts before actually submitting. Current submissions states:
  - Draft: Drafts are only visible to users with a read permissions to the submission itself, users with
  read submissions permissions for the form don't see them
  - Submitted: Submitted submissions are visible to users with a read submissions permissions for the form.
  Users with read permissions for the submission itself have s subset of the permissions listed in
  Form.allowedActionsWhenSubmitted
- Add allowedSubmissionStates to the form to allow the specification whether draft submissions are allowed for a form

## v0.4.21

- Stop logging personal data on schema violation

## v0.4.20

- Form: Allow resetting availableStarts and availabilityEnds to null on PATCH
- Add debug flag to FormalizeService

## v0.4.19

- Form: Add migration to add the demo form and permissions for it

## v0.4.18

- Form: Restore JSON string typed 'dataFeedSchema' since there is only partial support for free form objects in api-platform (e.g. standard
compliant merge-patch does not work)
- Submission: Restore JSON string typed 'dataFeedElement' since there is only partial support for free form objects in api-platform (e.g. standard
compliant merge-patch does not work)

## v0.4.16

- Add API tests
- Form: Replace JSON encoded string 'dataFeedSchema' by direct JSON object 'dataSchema'
- Deprecate 'dataFeedSchema'
- Submission: Replace JSON encoded string 'dataFeedElement' by direct JSON object 'data'
- Deprecate 'dataFeedElement'
- Replace yaml resource config by ApiResource annotations
- Allow empty submissions

## v0.4.15

- Re-allow application/json accept header for POST submissions for legacy system

## v0.4.14

- Add support for newer doctrine dbal/orm

## v0.4.13

- Re-allow application/json content-type for POST submissions for legacy system

## v0.4.12

- Update core (new ApiError)

## v0.4.11

- Drop support for Symfony v5
- Drop support for api-platform v2
- Add support for justinrainbow/json-schema v6 in addition to v5

## v0.4.10

- Update core and adapt function signatures

## v0.4.9

- guess and set form schema on first form submission (if not yet set), dropping validation of submissions by comparing the
data feed element keys with those of prior submission
- add basic output validation support to GET submission collection operations (only return submissions whose data feed element (JSON)
keys comply to those of the form schema)

## v0.4.8

- return granted actions for Form resources
- cache granted actions for one request

## v0.4.7

- Update authorization to v0.2

## v0.4.6

- Update authorization

## v0.4.5

- Remove parameter 'getAll' and implement the following get submission collection behaviour: The operation returns all 
submissions that the current user is authorized to read (all submissions of forms where they have a 'read_submissions' grant for and
single submissions that they are authorized to read, e.g. that they have posted). The parameter 'formIdentifier' is now optional and
can be considered as filter to list of submissions, returning only the submissions of the specified form that current user is
authorized to read (NOTE: it does neither throw 404 'not found' nor 403 'forbidden')

## v0.4.4

- Add submission level authorization as a new form attribute
- Enable cascade delete for form submissions on form deletion
- Add a new parameter 'getAll' to the GET submission collection operation. If specified, all form submissions are returned.
Otherwise, only the form submissions the logged-in user is granted to read are returned (requires submission level authorization
to be enabled in the form)

## v0.4.3

- Fix migration

## v0.4.0

- Replace user attribute based authorization by the resource-action-grant based authorization from the new 
dbp/relay-authorization-bundle

#### v0.3.24

- Port to PHPUnit 10

#### v0.3.23

- Port from doctrine annotations to php attributes

#### v0.3.22

- Fix form patch response with api-platform 3.2

#### v0.3.21

- Add support for api-platform 3.2

#### v0.3.20

- Change Content-Type for PATCH operations to "application/merge-patch+json"

#### v0.3.18

- Add support for Symfony 6

#### v0.3.16

- Drop support for PHP 7.4/8.0

#### v0.2.3

- Port to the new api-platform metadata system

#### v0.2.2

- Update to api-platform v2.7
