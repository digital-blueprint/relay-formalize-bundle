<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProcessor;
use Symfony\Component\HttpFoundation\Response;

class SubmissionProcessorTest extends RestTestCase
{
    private DataProcessorTester $submissionProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $submissionProcessor = new SubmissionProcessor($this->formalizeService, $this->authorizationService);
        $this->submissionProcessorTester = DataProcessorTester::create(
            $submissionProcessor, Submission::class, ['FormalizeSubmission:input']);
    }

    public function testAddSubmission()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{}');

        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
    }

    public function testAddSubmissionWithoutPermission()
    {
        $form = $this->addForm();

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{}');

        try {
            $this->submissionProcessorTester->addItem($submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateSubmission()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form, '{"firstName" : "John"}');

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals('{"firstName" : "John"}', $submissionPersistence->getDataFeedElement());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionPersistence->setDataFeedElement('{"firstName" : "Joni"}');

        $this->submissionProcessorTester->updateItem($submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals('{"firstName" : "Joni"}', $this->getSubmission($submission->getIdentifier())->getDataFeedElement());
    }

    public function testUpdateFormWithoutPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form, '{"firstName" : "John"}');

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $submissionPersistence->setDataFeedElement('{"firstName" : "Joni"}');

        try {
            $this->submissionProcessorTester->updateItem($submission->getIdentifier(), $submissionPersistence, $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateFormWrongPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form, '{"firstName" : "John"}');

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $submissionPersistence->setDataFeedElement('{"firstName" : "Joni"}');

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProcessorTester->updateItem($submission->getIdentifier(), $submissionPersistence, $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveSubmission()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
    }

    public function testRemoveSubmissionWithoutPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        try {
            $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveSubmissionWithWrongPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
