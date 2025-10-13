<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;

class AuthorizationIntegrationTest extends AbstractTestCase
{
    public function testAddAndRemoveFormResource()
    {
        $form = new Form();
        $form->setName('Testform');

        $form = $this->formalizeService->addForm($form);
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION));

        $this->formalizeService->removeForm($form);
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION));
    }

    public function testSubmissionLevelAuthorizationAddAndRemoveSubmissionResource()
    {
        $form = $this->testEntityManager->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION],
        );

        // for submitted submissions, the creator should have only grants for the form's actionsAllowedWhenSubmitted.
        // test entering the submitted state directly during add (POST).
        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_SUBMITTED);

        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::MANAGE_ACTION));

        $this->formalizeService->removeSubmission($submission);
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
    }

    public function testSubmissionLevelAuthorizationAddAndUpdateSubmissionResource()
    {
        $form = $this->testEntityManager->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION],
        );

        // for draft submissions, the creator should have a manage grant
        $submission = new Submission();
        $submission->setForm($form);
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_DRAFT);

        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION)); // manage implies update

        $this->authorizationService->clearCaches();
        $this->formalizeService->removeSubmission($submission);
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION));

        // for submitted submissions, the creator should have only grants for the form's actionsAllowedWhenSubmitted.
        // test transitioning from draft to submitted state on update (PATCH):
        $this->authorizationService->clearCaches();
        $submission = $this->formalizeService->addSubmission($submission);
        $previousSubmission = clone $submission;

        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_SUBMITTED);

        $this->authorizationService->clearCaches();
        $submission = $this->formalizeService->updateSubmission($submission, $previousSubmission);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::MANAGE_ACTION));

        $this->authorizationService->clearCaches();
        $this->formalizeService->removeSubmission($submission);
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
    }

    public function testNoSubmissionLevelAuthorizationAddAndRemoveSubmissionResource()
    {
        // No grants at all for creator-based submission authorization
        $form = $this->testEntityManager->addForm(grantBasedSubmissionAuthorization: false);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION));
    }

    public function testSubmissionLevelAuthorizationRemoveAllSubmissionResourcesOnRequest(): void
    {
        $form = $this->testEntityManager->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION],
        );

        $submission1 = new Submission();
        $submission1->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission1->setForm($form);
        $submission1 = $this->formalizeService->addSubmission($submission1);
        $submission2 = new Submission();
        $submission2->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission2->setForm($form);
        $submission2 = $this->formalizeService->addSubmission($submission2);
        $submission3 = new Submission();
        $submission3->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission3->setForm($form);
        $submission3 = $this->formalizeService->addSubmission($submission3);

        $this->assertCount(3,
            $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));

        $this->formalizeService->removeAllSubmittedFormSubmissions($form->getIdentifier());

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));

        $this->assertCount(0,
            $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));
    }

    public function testSubmissionLevelAuthorizationRemoveAllSubmissionResourcesOnDeleteForm()
    {
        $form = $this->testEntityManager->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION],
        );

        $submission1 = new Submission();
        $submission1->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission1->setForm($form);
        $submission1 = $this->formalizeService->addSubmission($submission1);
        $submission2 = new Submission();
        $submission2->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission2->setForm($form);
        $submission2 = $this->formalizeService->addSubmission($submission2);
        $submission3 = new Submission();
        $submission3->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission3->setForm($form);
        $submission3 = $this->formalizeService->addSubmission($submission3);

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));

        $this->formalizeService->removeForm($form);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedItemAction(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION));
    }
}
