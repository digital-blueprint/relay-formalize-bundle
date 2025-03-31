# Formalize Authorization

The current version of Formalize requires all users to be authenticated to be able to use the web API.

## Form-Level Authorization

### Form Collection Actions

- `create` The holder of the grant may create (POST) new forms. The permission to create new forms may initially be granted
in the bundle configuration of the [DbpRelayAuthorizationBundle](https://github.com/digital-blueprint/relay-authorization-bundle).
- `get` The get collection action does not require a grant. It just returns the forms, the logged-in user may read.

### Form Item Actions

- `manage` The holder of the grant may issue grants for the form to other users, and is implicitly granted all
  actions on the form.
- `read` The holder of the grant may read (GET) the form
- `update` The holder of the grant may update (PATCH) the form
- `delete` The holder of the grant may delete (DELETE) the form
- `create_submissions` The holder of the grant may create (POST) submissions to the form
- `read_submissions` The holder of the grant may read (GET) submissions of the form
- `update_submissions` The holder of the grant may update (PATCH) submissions of the form
- `delete_submissions` The holder of the grant may delete (DELETE) submissions of the form

**Note**: The logged-in user that creates a form automatically acquires `manage` permissions for the form.

## Submission-Level Authorization

### Submission Collection Actions

- The permission to create submissions is always bound to form item. Hence, to be allowed to create submissions to a form,
need a `create_submissions` grant for the form
- The get submission collection request requires a form to be specified to get submissions for.
If the logged-in user has a `read_submissions` grant for the form, they are allowed to read **all submissions** of the form.
Otherwise, the user is only allowed to read submissions that they have read permissions for, i.e. the submissions they created themselves
or have been shared with them.

### Submission Item Actions

- `read` The holder of the grant may read (GET) the submission
- `update` The holder of the grant may update (PATCH) the submission
- `delete` The holder of the grant may delete (DELETE) the submission

### Allowed Actions when Submitted

Defines the (maximum) set of actions that a user is allowed to perform once a submission is in `submitted` state.
(form attribute: `allowedActionsWhenSubmitted`):

- `[]` users may neither read nor edit nor delete their submissions
- `[..., 'read', ...]` users may read (GET) their own submissions
- `[..., 'update', ...]` users may update (PATCH) their own submissions
- `[..., 'delete', ...]` users may delete (DELETE) their own submissions

**Note**: Drafts can be generally be read, updated and deleted by their creator.

### Creator-Based Authorization

There are two different ways to handle submission-level authorization. The default is the creator-based authorization, where 
the creator (user that posted the submission) is allowed to perform all `allowedActionsWhenSubmitted`.

Creator-based authorization is used when the form attribute `grantBasedSubmissionAuthorization` is `true`.

### Grant-Based Authorization

The second way is grant-based authorization. If enabled, authorization grants are issued at 
submission level. It adds a new action: 

- `manage` The holder of the grant may issue grants for the submission to other users ("share" the submission), and
  is implicitly granted all other submission item actions

The creator of a submission is automatically issued a `manage` grant. 

**Note**: The actual set of allowed actions is the overlap of the form attribute `allowedActionsWhenSubmitted` and
the grants that the user has for the submission.

Grant-based authorization is used when the form attribute `grantBasedSubmissionAuthorization` is `false`.
