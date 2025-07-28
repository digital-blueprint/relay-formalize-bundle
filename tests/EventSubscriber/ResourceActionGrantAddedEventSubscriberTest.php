<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\EventSubscriber;

use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;

class ResourceActionGrantAddedEventSubscriberTest extends AbstractTestCase
{
    public function testSubmissionGrantAddedEvent(): void
    {
        $this->assertNull($this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent());

        $form = $this->testEntityManager->addForm(grantBasedSubmissionAuthorization: true);

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setForm($form);

        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertNotNull($this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent());
        $this->assertEquals($submission,
            $this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent()->getSubmission());
    }
}
