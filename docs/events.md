# Events

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
