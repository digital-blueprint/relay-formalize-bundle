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

    public function testGetSubmissionItemWithReadFormSubmissionsPermission()
    {
        // user has a grant to read all submissions of a form
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
    }

    public function testGetSubmissionItemDraftWithReadFormSubmissionsPermission()
    {
        // users with read form submissions permissions are not authorized to read drafts
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('ApiError was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionItemGrantBasedAuthorization()
    {
        // user has a grant to read a submission of a form (with grant-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
    }

    public function testGetSubmissionItemGrantBasedAuthorizationReadNotAllowedWhenSubmitted()
    {
        // user has a grant to read a submission of a form (with grant-based submission authorization),
        // however, read is not allowed when the submission is in submitted state
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('ApiError was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionItemDraftGrantBasedAuthorization()
    {
        // user has a grant to read a submission of a form (with grant-based submission authorization),
        // however read is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
    }

    public function testGetSubmissionItemCreatorBasedAuthorization()
    {
        // user may read their own submission to a form (with creator-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form);

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
    }

    public function testGetSubmissionItemCreatorBasedAuthorizationReadNotAllowedWhenSubmitted()
    {
        // user may read their own submission to a form (with creator-based submission authorization),
        // however, read is not allowed when the submission is in submitted state
        $form = $this->addForm(grantBasedSubmissionAuthorization: false, actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('ApiError was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionItemDraftCreatorBasedAuthorization()
    {
        // user may read their own submission to a form (with creator-based submission authorization),
        // however, read is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
    }

    public function testGetSubmissionItemWithoutPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('ApiError was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionItemWithWrongFormPermissions()
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

    public function testGetSubmissionCollectionWithFormIdentifierFormNotFound()
    {
        $form = $this->addForm();
        $this->addSubmission($form);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => 'nope',
        ]);
        $this->assertCount(0, $submissions);
    }

    public function testGetSubmissionCollectionWithFormIdentifierWithReadFormSubmissionsGrant()
    {
        $form = $this->addForm();
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);
        $submission3 = $this->addSubmission($form);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
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

        // should work the same for forms with subission level authorization enabled:
        $form2 = $this->addForm('Testform 2', null, true);
        $submission2_1 = $this->addSubmission($form2);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));

        // test pagination:
        $this->authorizationService->clearCaches();
        $submissionPage1 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'page' => '1',
            'perPage' => '2',
        ]);
        $this->assertCount(2, $submissionPage1);

        $this->authorizationService->clearCaches();
        $submissionPage2 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'page' => '2',
            'perPage' => '2',
        ]);
        $this->assertCount(1, $submissionPage2);

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

    public function testGetSubmissionCollectionWithFormIdentifierWithSubmissionLevelAuthorization()
    {
        $form = $this->addForm('Testform', null, true);
        $submission1 = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);
        $submission3 = $this->addSubmission($form);
        $submission4 = $this->addSubmission($form);

        $form2 = $this->addForm('Testform 2', null, true);
        $submission2_1 = $this->addSubmission($form2);
        $submission2_2 = $this->addSubmission($form2);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

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

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER.'_2');

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $this->authorizationService->clearCaches();
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

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        // test pagination:
        $this->login(self::CURRENT_USER_IDENTIFIER.'_2');

        $this->authorizationService->clearCaches();
        $submissionPage1 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'page' => '1',
            'perPage' => '2',
        ]);
        $this->assertCount(2, $submissionPage1);

        $this->authorizationService->clearCaches();
        $submissionPage2 = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'page' => '2',
            'perPage' => '2',
        ]);
        $this->assertCount(1, $submissionPage2);

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

    public function testGetSubmissionCollectionWithFormIdentifierWithoutSubmissionLevelAuthorization()
    {
        $form = $this->addForm('Testform', null, false);
        $submission = $this->addSubmission($form);

        // user has grant but submission level authorization is not enabled for this form
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);
    }

    public function testGetSubmissionCollectionWithoutFormIdentifier()
    {
        $form1 = $this->addForm('Testform', null, true);
        $submission1_1 = $this->addSubmission($form1);

        $form2 = $this->addForm('Testform 2', null, false);
        $submission2_1 = $this->addSubmission($form2);
        $submission2_2 = $this->addSubmission($form2);

        $form3 = $this->addForm('Testform 3', null, true);
        $submission3_1 = $this->addSubmission($form3);
        $submission3_2 = $this->addSubmission($form3);
        $submission3_3 = $this->addSubmission($form3);
        $submission3_4 = $this->addSubmission($form3);

        $submissions = $this->submissionProviderTester->getCollection();
        $this->assertCount(0, $submissions);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection();
        $this->assertCount(0 + 0 + 1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection();
        $this->assertCount(1 + 0 + 1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection();
        $this->assertCount(1 + 2 + 1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));

        // user has read form submission grant for form1 and a single submission grant for a submission of form1
        // -> result size must be the same since submissions must be unique
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection();
        $this->assertCount(1 + 2 + 1, $submissions);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_4->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        // all in one page:
        $submissions = $this->submissionProviderTester->getCollection();
        $this->assertCount(1 + 2 + 4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_4) {
            return $submission->getIdentifier() === $submission3_4->getIdentifier();
        }));

        // test pagination:
        // the pagination algorithm first returns the 'read_submissions' form submissions (form1 and form2 => 3 submissions),
        // then the single submission grant submissions (form3 => 4 submissions)

        // page size 2: we have one mixed page of form submissions and single grant submission
        $submissionPage1 = $this->submissionProviderTester->getCollection([
            'page' => '1',
            'perPage' => '2',
        ]);
        $this->assertCount(2, $submissionPage1);

        $submissionPage2 = $this->submissionProviderTester->getCollection([
            'page' => '2',
            'perPage' => '2',
        ]);
        $this->assertCount(2, $submissionPage2);

        $submissionPage3 = $this->submissionProviderTester->getCollection([
            'page' => '3',
            'perPage' => '2',
        ]);
        $this->assertCount(2, $submissionPage3);

        $submissionPage4 = $this->submissionProviderTester->getCollection([
            'page' => '4',
            'perPage' => '2',
        ]);
        $this->assertCount(1, $submissionPage4);

        $submissionPage5 = $this->submissionProviderTester->getCollection([
            'page' => '5',
            'perPage' => '2',
        ]);
        $this->assertCount(0, $submissionPage5);

        $submissions = array_merge($submissionPage1, $submissionPage2, $submissionPage3, $submissionPage4);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_4) {
            return $submission->getIdentifier() === $submission3_4->getIdentifier();
        }));

        // page size 3: we don't have a mixed page
        $submissionPage1 = $this->submissionProviderTester->getCollection([
            'page' => '1',
            'perPage' => '3',
        ]);
        $this->assertCount(3, $submissionPage1);

        $submissionPage2 = $this->submissionProviderTester->getCollection([
            'page' => '2',
            'perPage' => '3',
        ]);
        $this->assertCount(3, $submissionPage2);

        $submissionPage3 = $this->submissionProviderTester->getCollection([
            'page' => '3',
            'perPage' => '3',
        ]);
        $this->assertCount(1, $submissionPage3);

        $submissionPage4 = $this->submissionProviderTester->getCollection([
            'page' => '4',
            'perPage' => '3',
        ]);
        $this->assertCount(0, $submissionPage4);

        $submissions = array_merge($submissionPage1, $submissionPage2, $submissionPage3);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_4) {
            return $submission->getIdentifier() === $submission3_4->getIdentifier();
        }));

        // page size 4: first page is mixed page
        $submissionPage1 = $this->submissionProviderTester->getCollection([
            'page' => '1',
            'perPage' => '4',
        ]);
        $this->assertCount(4, $submissionPage1);

        $submissionPage2 = $this->submissionProviderTester->getCollection([
            'page' => '2',
            'perPage' => '4',
        ]);
        $this->assertCount(3, $submissionPage2);

        $submissionPage3 = $this->submissionProviderTester->getCollection([
            'page' => '3',
            'perPage' => '4',
        ]);
        $this->assertCount(0, $submissionPage3);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3_4) {
            return $submission->getIdentifier() === $submission3_4->getIdentifier();
        }));
    }
}
