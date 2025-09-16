# Submission State Workflow

There are two submission states: `draft` and `submitted`. The set of allowed states for a form
can be configured in the form attribute `allowedSubmissionStates`.
By default, the only allowed state is `submitted`.

## Drafts

Drafts, i.e. submissions in the `draft` state can only be accessed (read, updated, and deleted) 
by their creators and people who were granted ("shared") the respective permissions to the draft.

**Note**: Sharing of drafts is only possible if [Grant-Based Authorization](./authorization.md#grant-based-authorization)
is enabled for the form.

## Submissions

Submissions in the `submitted` state can be accessed by users with form level submission permissions:

- `read_submissions` Read all submission of the form
- `update_submissions` Update all submissions of the form
- `delete_submissions` Delete all submissions of the form

Submissions in the `submitted` state can be accessed by users with submission level submission permissions
if the respective granted actions are allowed for submissions of the form
(see [Allowed Actions when Submitted](./authorization.md#allowed-actions-when-submitted))
