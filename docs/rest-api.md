# REST Web API

### POST `/formalize/submissions`

Create a new submission:
- `formIdentifier` is the identifier of the form to which the submission belongs (required).
- `dataFeedElement` is a JSON string that encodes that data attributes and values to be submitted.
- `submissionState` Possible values: `draft`, `submitted`. Default value: `submitted`.

| relay:errorId                       | Status code | Description                                     | relay:errorDetails                   | Example                          |
|-------------------------------------|-------------|-------------------------------------------------|--------------------------------------|----------------------------------|
| `formalize:submission-not-created`  | 500         | The submission could not be created.            |                                      |                                  |
| `formalize:submission-invalid-json` | 400         | The dataFeedElement doesn't contain valid json. | `[['<validation error 1>', ...]`     | `['property "foo" is required']` |
| `formalize:required-field-missing`  | 422         | A required field is missing.                    | `['<field name>']`                   | `['formIdentifier']`             |

### GET `/formalize/submissions`

Get the submissions for a requested form that the use may read:
- `formIdentifier` is the identifier of the form to get submissions for (required).
- `outputValidation` is the type of output validation to use (default: `NONE`)

| relay:errorId                                    | Status code | Description                            | relay:errorDetails | Example              |
|--------------------------------------------------|-------------|----------------------------------------|--------------------|----------------------|
| `formalize:getting-submission-collection-failed` | 500         | Failed to get submission collection.   |                    |                      |
| `formalize:required-field-missing`               | 400         | A required field is missing.           | `['<field name>']` | `['formIdentifier']` |

### DELETE `/formalize/submissions`

Delete all submissions of a specifier form (except for drafts):
- `formIdentifier` is the identifier of the form to delete submissions of (required).

| relay:errorId                             | Status code | Description                            | relay:errorDetails  | Example              |
|-------------------------------------------|-------------|----------------------------------------|---------------------|----------------------|
| `formalize:required-field-missing`        | 400         | A required field is missing.           | `['<field name>']`  | `['formIdentifier']` |
| `formalize:form-not-found`                | 404         | Form was not found.                    |                     |                      |
| `formalize:form-submissions-not-removed`  | 500         | Failed to delete all form submissions. |                     |                      |

### GET `/formalize/submissions/{identifier}`

Get the submission with the given identifier.

| relay:errorId                              | Status code | Description                    | relay:errorDetails | Example |
|--------------------------------------------|-------------|--------------------------------| ------------------ | ------- |
| `formalize:submission-not-found`           | 404         | Submission was not found.      |                    |         |
| `formalize:getting-submission-item-failed` | 500         | Failed to get submission item. |                    |         |


### PATCH `/formalize/submissions/{identifier}`

Update the submission with the given identifier:
- `dataFeedElement` is a JSON string that encodes that data attributes and values to be submitted.
- `submissionState` Possible values: `draft`, `submitted`. Default value: `submitted`.

| relay:errorId                      | Status code | Description                       | relay:errorDetails | Example |
|------------------------------------|-------------|-----------------------------------| ------------------ | ------- |
| `formalize:submission-not-updated` | 500         | Failed to update submission item. |                    |         |
| `formalize:submission-not-found`   | 404         | Submission was not found.         |                    |         |

### DELETE `/formalize/submissions/{identifier}`

Delete the submission with the given identifier.

| relay:errorId                      | Status code | Description                       | relay:errorDetails | Example |
|------------------------------------|-------------|-----------------------------------| ------------------ | ------- |
| `formalize:submission-not-deleted` | 500         | Failed to delete submission item. |                    |         |
| `formalize:submission-not-found`   | 404         | Submission was not found.         |                    |         |

### POST `/formalize/forms`

Create a new form item with the following attributes:
- `name` is the friendly name of the form (required).
- `dataFeedSchema` is a JSON string that encodes the form schema.
- `grantBasedSubmissionAuthorization` is the type of submission-level authorization to use.
- `allowedActionsWhenSubmitted` is the set of actions that a user is allowed to perform once a submission is in `submitted` state.
- `allowedSubmissionStates` is the set of allowed submission states for submissions.
- `availableStarts` is the date when the form becomes available for submissions.
- `availabilityEnds` is the date when the form stops accepting submissions.
- `maxNumSubmissionsPerCreator` is the maximum number of submissions a user is allowed to create for this form.

| relay:errorId                             | Status code | Description                                             | relay:errorDetails         | Example            |
|-------------------------------------------|-------------|---------------------------------------------------------|----------------------------|--------------------|
| `formalize:form-not-created`              | 500         | The form could not be created.                          |                            |                    |
| `formalize:form-invalid-data-feed-schema` | 400         | The `dataFeedSchema` doesn't contain valid JSON schema. | `['<validation error>']`   | `['syntax error']` |
| `formalize:required-field-missing`        | 422         | A required field is missing.                            | `['<field name>']`         | `['name']`         |

### GET `/formalize/forms`

Get the collection of forms the user is allowed to read.
- `whereReadFormSubmissionsGranted` limits the form results to those that the user has `read_submissions` permissions for
  (even if it has no submissions) or has submissions that they may read (e.g. their own submissions).

| relay:errorId                              | Status code | Description                    | relay:errorDetails | Example            |
|--------------------------------------------|-------------|--------------------------------|--------------------|--------------------|
| `formalize:getting-form-collection-failed` | 500         | Failed to get form collection. |                    |                    |


### GET `/formalize/forms/{identifier}`

Get the form with the given identifier.

| relay:errorId                        | Status code | Description              | relay:errorDetails | Example |
|--------------------------------------|-------------|--------------------------| ------------------ | ------- |
| `formalize:form-not-found`           | 404         | Form was not found.      |                    |         |
| `formalize:getting-form-item-failed` | 500         | Failed to get form item. |                    |         |

### PATCH `/formalize/forms/{identifier}`

Update the form with the given identifier. See the POST request for available form attributes.

| relay:errorId                | Status code | Description                 | relay:errorDetails | Example |
|------------------------------|-------------|-----------------------------| ------------------ | ------- |
| `formalize:form-not-found`   | 404         | Form was not found.         |                    |         |
| `formalize:form-not-updated` | 500         | Failed to update form item. |                    |         |

### DELETE `/formalize/forms/{identifier}`
 
Delete the form with the given identifier.

| relay:errorId                | Status code | Description                 | relay:errorDetails | Example |
|------------------------------|-------------|-----------------------------| ------------------ | ------- |
| `formalize:form-not-found`   | 404         | Form was not found.         |                    |         |
| `formalize:form-not-removed` | 500         | Failed to delete form item. |                    |         |
