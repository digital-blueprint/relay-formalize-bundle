<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProvider;
use Symfony\Component\HttpFoundation\Response;

class SubmissionProviderTest extends RestTest
{
    private DataProviderTester $submissionProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $submissionProvider = new SubmissionProvider($this->formalizeService, $this->authorizationService);
        DataProviderTester::setUp($submissionProvider);

        $this->submissionProviderTester = new DataProviderTester(
            $submissionProvider, Submission::class, ['FormalizeSubmission:output']);
    }

    public function testGetSubmissionItemWithReadPermission()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::READ_SUBMISSIONS_OF_FORM_ACTION) => true,
            ]);

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
    }

    public function testGetSubmissionItemWithoutPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionItemWithWrongPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::READ_ACTION) => true,
            ]);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionCollection()
    {
        $form = $this->addForm();
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::READ_SUBMISSIONS_OF_FORM_ACTION) => true,
            ]);

        $submissions = $this->submissionProviderTester->getCollection(['formIdentifier' => $form->getIdentifier()]);
        $this->assertCount(2, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());
        $this->assertEquals($submission2->getIdentifier(), $submissions[1]->getIdentifier());
    }

    public function testGetSubmissionCollectionMissingFormIdentifier()
    {
        $form = $this->addForm();
        $this->addSubmission($form);
        $this->addSubmission($form);

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::READ_SUBMISSIONS_OF_FORM_ACTION) => true,
            ]);

        try {
            $this->submissionProviderTester->getCollection([]);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-parameter-missing', $apiError->getErrorId());
            $this->assertEquals(['formIdentifier'], $apiError->getErrorDetails());
        }
    }

    public function testGetSubmissionCollectionWithoutPermission()
    {
        $form = $this->addForm();
        $this->addSubmission($form);
        $this->addSubmission($form);

        try {
            $this->submissionProviderTester->getCollection(['formIdentifier' => $form->getIdentifier()]);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionCollectionWithWrongPermission()
    {
        $form = $this->addForm();
        $this->addSubmission($form);
        $this->addSubmission($form);

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::WRITE_SUBMISSIONS_OF_FORM_ACTION) => true,
            ]);

        try {
            $this->submissionProviderTester->getCollection(['formIdentifier' => $form->getIdentifier()]);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
