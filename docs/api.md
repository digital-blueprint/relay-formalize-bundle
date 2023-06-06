# API

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
