<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
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
        DataProcessorTester::setUp($submissionProcessor);

        $this->submissionProcessorTester = new DataProcessorTester(
            $submissionProcessor, Submission::class, ['FormalizeSubmission:input']);
    }

    public function testAddSubmission()
    {
        $form = $this->addForm();

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::ADD_SUBMISSIONS_TO_FORM_ACTION) => true,
            ]);

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

    public function testUpdateForm()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form, '{"firstName" : "John"}');

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals('{"firstName" : "John"}', $submissionPersistence->getDataFeedElement());

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::WRITE_SUBMISSIONS_OF_FORM_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::ADD_SUBMISSIONS_TO_FORM_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::WRITE_SUBMISSIONS_OF_FORM_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::WRITE_ACTION) => true,
            ]);

        try {
            $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
