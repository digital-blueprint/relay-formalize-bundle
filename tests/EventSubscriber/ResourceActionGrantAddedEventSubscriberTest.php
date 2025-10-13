<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\EventSubscriber;

use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;

class ResourceActionGrantAddedEventSubscriberTest extends AbstractTestCase
{
    public function testSubmissionGrantAddedEventS(): void
    {
        $this->assertNull($this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent());

        $form = $this->testEntityManager->addForm(grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT);

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_DRAFT);
        $submission->setForm($form);

        // on posting a submission draft, a manage submission grant is added for the current user
        // however, if formalize itself is adding a grant, the event is suspended
        $this->formalizeService->addSubmission($submission);

        $this->assertNull($this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent());

        // share the submission with another user
        $this->resourceActionGrantService->addResourceActionGrant(AuthorizationService::SUBMISSION_RESOURCE_CLASS,
            $submission->getIdentifier(), AuthorizationService::READ_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $submissionGrantAddedEvent = $this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent();
        $this->assertNotNull($submissionGrantAddedEvent);
        $this->assertEquals($submission, $submissionGrantAddedEvent->getSubmission());
        $this->assertEquals(AuthorizationService::READ_SUBMISSION_ACTION, $submissionGrantAddedEvent->getAction());
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER, $submissionGrantAddedEvent->getUserIdentifier());
        $this->assertNull($submissionGrantAddedEvent->getGroupIdentifier());
        $this->assertNull($submissionGrantAddedEvent->getDynamicGroupIdentifier());
    }
}
