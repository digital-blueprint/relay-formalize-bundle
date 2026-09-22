<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\EventSubscriber;

use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;

class ResourceActionGrantAddedEventSubscriberTest extends AbstractTestCase
{
    public function testSubmissionGrantAddedEventS(): void
    {
        $this->assertNull($this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent());

        $form = $this->testEntityManager->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT
        );

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_DRAFT);
        $submission->setForm($form);

        // on posting a submission draft, a manage submission grant is added for the current user
        // however, if formalize itself is adding a grant, the event is suspended
        $this->formalizeService->addSubmission($submission);

        $this->assertNull($this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent());

        // share the submission with another user
        $this->resourceActionGrantService->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS,
            $submission->getIdentifier(),
            action: AuthorizationService::READ_SUBMISSION_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        $submissionGrantAddedEvent = $this->testSubmissionEventSubscriber->getSubmissionGrantAddedEvent();
        $this->assertNotNull($submissionGrantAddedEvent);
        $this->assertEquals($submission, $submissionGrantAddedEvent->getSubmission());
        $this->assertEquals(AuthorizationService::READ_SUBMISSION_ACTION, $submissionGrantAddedEvent->getResourceActionGrant()->getAction());
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER, $submissionGrantAddedEvent->getResourceActionGrant()->getUserIdentifier());
        $this->assertNull($submissionGrantAddedEvent->getResourceActionGrant()->getUserGroup());
        $this->assertNull($submissionGrantAddedEvent->getResourceActionGrant()->getDynamicUserGroupIdentifier());
    }

    public function testFormGrantAddedEvent(): void
    {
        $this->assertNull($this->testSubmissionEventSubscriber->getFormGrantAddedEvent());

        $form = new Form();
        $form->setName('Test Form');
        $this->formalizeService->addForm($form);

        // during form creation, where formalize adds a manage grant for the new form,
        // the event should be suspended
        $this->assertNull($this->testSubmissionEventSubscriber->getFormGrantAddedEvent());

        // share the form with another user
        $this->resourceActionGrantService->addResourceActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS,
            $form->getIdentifier(),
            action: AuthorizationService::READ_FORM_ACTION,
            userIdentifier: self::ANOTHER_USER_IDENTIFIER);

        $formGrantAddedEvent = $this->testSubmissionEventSubscriber->getFormGrantAddedEvent();
        $this->assertNotNull($formGrantAddedEvent);
        $this->assertEquals($form, $formGrantAddedEvent->getForm());
        $this->assertEquals(AuthorizationService::READ_FORM_ACTION, $formGrantAddedEvent->getResourceActionGrant()->getAction());
        $this->assertEquals(self::ANOTHER_USER_IDENTIFIER, $formGrantAddedEvent->getResourceActionGrant()->getUserIdentifier());
        $this->assertNull($formGrantAddedEvent->getResourceActionGrant()->getUserGroup());
        $this->assertNull($formGrantAddedEvent->getResourceActionGrant()->getDynamicUserGroupIdentifier());
    }
}
