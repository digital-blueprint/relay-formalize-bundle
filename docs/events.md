# Events

To extend the behavior of the bundle the following event is registered:

### CreateSubmissionPostEvent

This event allows you to react to submissions, i.e. when submissions enter the `submitted` state.
You can use this for example to send a registration confirmation to the registrant.

**Example**: Create an event subscriber instance (e.g. `src/EventSubscriber/SubmissionSubmittedSubscriber.php`) and
register it as a service:

```php
<?php

namespace App\EventSubscriber;

use Dbp\Relay\FormalizeBundle\Event\SubmissionSubmittedPostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SubmissionSubmittedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SubmissionSubmittedPostEvent::class => 'onPost',
        ];
    }

    public function onPost(SubmissionSubmittedPostEvent $event)
    {
        $submission = $event->getSubmission();
        $dataFeedElement = $submission->getDataFeedElementDecoded();

        // e.g. write an email to the registrant
        $email = $dataFeedElement['email'] ?? null;
        ...
    }
}
```
