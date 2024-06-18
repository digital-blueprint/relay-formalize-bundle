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
        // manage action:
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            [ResourceActionGrantService::MANAGE_ACTION]));

        $this->formalizeService->removeForm($form);
        // manage action:
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            [ResourceActionGrantService::MANAGE_ACTION]));
    }

    public function testSubmissionLevelAuthorizationAddAndRemoveSubmissionResource()
    {
        $form = $this->addFormWithSubmissionLevelAuthorization();

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        $submission = $this->formalizeService->addSubmission($submission);

        // manage action:
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            [ResourceActionGrantService::MANAGE_ACTION]));

        $this->formalizeService->removeSubmission($submission);
        // manage action:
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            [ResourceActionGrantService::MANAGE_ACTION]));
    }

    public function testNoSubmissionLevelAuthorizationAddAndRemoveSubmissionResource()
    {
        $form = $this->addFormWithSubmissionLevelAuthorization(false);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        $submission = $this->formalizeService->addSubmission($submission);

        // manage action:
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            [ResourceActionGrantService::MANAGE_ACTION]));
    }

    public function testSubmissionLevelAuthorizationRemoveAllSubmissionResourcesOnRequest(): void
    {
        $form = $this->addFormWithSubmissionLevelAuthorization();

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

        $this->assertCount(3, $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));

        $this->formalizeService->removeAllFormSubmissions($form->getIdentifier());

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));

        $this->assertCount(0, $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));
    }

    public function testSubmissionLevelAuthorizationRemoveAllSubmissionResourcesOnDeleteForm()
    {
        $form = new Form();
        $form->setName('Testform');
        $form->setSubmissionLevelAuthorization(true);

        $form = $this->formalizeService->addForm($form);

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

        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertTrue($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));

        $this->formalizeService->removeForm($form);

        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
        $this->assertFalse($this->resourceActionGrantService->isCurrentUserGrantedAnyOfItemActions(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(), [ResourceActionGrantService::MANAGE_ACTION]));
    }

    private function addFormWithSubmissionLevelAuthorization(bool $submissionLevelAuthorization = true): Form
    {
        return $this->testEntityManager->addForm('Testform', null, $submissionLevelAuthorization);
    }
}
