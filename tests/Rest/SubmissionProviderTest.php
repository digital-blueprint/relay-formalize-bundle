<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProvider;
use Symfony\Component\HttpFoundation\Response;

class SubmissionProviderTest extends RestTestCase
{
    private DataProviderTester $submissionProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $submissionProvider = new SubmissionProvider($this->formalizeService, $this->authorizationService);
        $this->submissionProviderTester = DataProviderTester::create(
            $submissionProvider, Submission::class, ['FormalizeSubmission:output']);
    }

    public function testGetSubmissionItemWithReadPermission()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

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

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionCollectionGetAll()
    {
        $form = $this->addForm();
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);
        $submission3 = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'getAll' => '',
        ]);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1) {
            return $submission->getIdentifier() === $submission1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2) {
            return $submission->getIdentifier() === $submission2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3) {
            return $submission->getIdentifier() === $submission3->getIdentifier();
        }));
    }

    public function testGetSubmissionCollectionGetAllPagination()
    {
        $form = $this->addForm();
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);
        $submission3 = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionPage1 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'getAll' => '',
            'page' => '1',
            'perPage' => '2',
        ]);
        $this->assertCount(2, $submissionPage1);

        $submissionPage2 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'getAll' => '',
            'page' => '2',
            'perPage' => '2',
        ]);
        $this->assertCount(1, $submissionPage2);

        $this->assertEmpty(array_uintersect($submissionPage1, $submissionPage2, function ($sub1, $sub2) {
            return strcmp($sub1->getIdentifier(), $sub2->getIdentifier());
        }));
        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1) {
            return $submission->getIdentifier() === $submission1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2) {
            return $submission->getIdentifier() === $submission2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3) {
            return $submission->getIdentifier() === $submission3->getIdentifier();
        }));
    }

    public function testGetSubmissionCollectionGetAllSubmissionLevelAuthorization()
    {
        // submission level authorization should not have an influence on 'get all' behavior
        $form = $this->addForm('Testform', null, true);
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_3');

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'getAll' => '',
        ]);
        $this->assertCount(2, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());
        $this->assertEquals($submission2->getIdentifier(), $submissions[1]->getIdentifier());
    }

    public function testGetSubmissionCollectionGetAllMissingFormIdentifier()
    {
        $form = $this->addForm();
        $this->addSubmission($form);
        $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProviderTester->getCollection([
                'getAll' => '',
            ]);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-parameter-missing', $apiError->getErrorId());
            $this->assertEquals(['formIdentifier'], $apiError->getErrorDetails());
        }
    }

    public function testGetSubmissionCollectionGetAllWithoutPermission()
    {
        $form = $this->addForm();
        $this->addSubmission($form);
        $this->addSubmission($form);

        try {
            $this->submissionProviderTester->getCollection([
                'formIdentifier' => $form->getIdentifier(),
                'getAll' => '',
            ]);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionCollectionGetAllWithWrongPermission()
    {
        $form = $this->addForm();
        $this->addSubmission($form);
        $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProviderTester->getCollection([
                'formIdentifier' => $form->getIdentifier(),
                'getAll' => '',
            ]);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionCollectionGrantedOnly()
    {
        $form = $this->addForm('Testform', null, true);
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);
        $submission3 = $this->addSubmission($form);
        $submission4 = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2) {
            return $submission->getIdentifier() === $submission2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3) {
            return $submission->getIdentifier() === $submission3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission4) {
            return $submission->getIdentifier() === $submission4->getIdentifier();
        }));

        $this->login(self::CURRENT_USER_IDENTIFIER.'_3');

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);
    }

    public function testGetSubmissionCollectionGrantedOnlyPagination(): void
    {
        $form = $this->addForm('Testform', null, true);
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);
        $submission3 = $this->addSubmission($form);
        $submission4 = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        // test pagination:
        $submissionPage1 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'page' => '1',
            'perPage' => '2',
        ]);
        $this->assertCount(2, $submissionPage1);

        $submissionPage2 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'page' => '2',
            'perPage' => '2',
        ]);
        $this->assertCount(1, $submissionPage2);

        $this->assertEmpty(array_uintersect($submissionPage1, $submissionPage2, function ($sub1, $sub2) {
            return strcmp($sub1->getIdentifier(), $sub2->getIdentifier());
        }));
        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2) {
            return $submission->getIdentifier() === $submission2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3) {
            return $submission->getIdentifier() === $submission3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission4) {
            return $submission->getIdentifier() === $submission4->getIdentifier();
        }));
    }

    public function testGetSubmissionCollectionGrantedOnlyNoSubmissionLevelAuthorization()
    {
        $form = $this->addForm('Testform', null, false);
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);
    }
}
