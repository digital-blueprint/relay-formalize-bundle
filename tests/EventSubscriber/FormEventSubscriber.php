<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\EventSubscriber;

use Dbp\Relay\FormalizeBundle\Event\FormAddedPostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            FormAddedPostEvent::class => 'onFormAddedPostEvent',
        ];
    }

    public function onFormAddedPostEvent(FormAddedPostEvent $event): void
    {
        $form = $event->getForm();
        $form->setAdditionalData([
            'event_handled' => true,
        ]);
    }
}
