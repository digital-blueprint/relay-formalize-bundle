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

    public function testGetSubmissionItemWithManageFormSubmissionsPermission()
    {
        // user has a grant to read all submissions of a form
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submission->getGrantedActions());
    }

    public function testGetSubmissionItemWithReadFormSubmissionsPermission()
    {
        // user has a grant to read all submissions of a form
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertEquals([AuthorizationService::READ_SUBMISSION_ACTION], $submissionPersistence->getGrantedActions());

        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertIsPermutationOf([AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION],
            $submissionPersistence->getGrantedActions());
    }

    public function testGetSubmissionItemWithMissingReadFormSubmissionsPermission()
    {
        // user has a grant to read all submissions of a form
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProviderTester->getItem($submission->getIdentifier());
            $this->fail('ApiError was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
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

        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertEquals([AuthorizationService::READ_SUBMISSION_ACTION], $submissionPersistence->getGrantedActions());
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

        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertEquals([AuthorizationService::READ_SUBMISSION_ACTION], $submissionPersistence->getGrantedActions());
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

        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertEquals(AuthorizationService::SUBMISSION_ITEM_ACTIONS, $submissionPersistence->getGrantedActions());
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

    public function testGetSubmissionCollectionFormNotFound()
    {
        $form = $this->addForm();
        $this->addSubmission($form);

        try {
            $this->submissionProviderTester->getCollection([
                'formIdentifier' => 'nope',
            ]);
            $this->fail('ApiError was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionCollectionWithManageFormGrant()
    {
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED);
        $submission1_1 = $this->addSubmission($form, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission1_2 = $this->addSubmission($form, creatorId: self::CURRENT_USER_IDENTIFIER);
        $submission1_3 = $this->addSubmission($form, creatorId: self::ANOTHER_USER_IDENTIFIER.'2');
        $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $draft = $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );

        // add some noise:
        $noiseForm = $this->addForm();
        $this->addSubmission($noiseForm);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissions);
        $this->assertEquals($draft->getIdentifier(), $submissions[0]->getIdentifier());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        // don't expect draft by another user
        $this->assertCount(4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_3) {
            return $submission->getIdentifier() === $submission1_3->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft) {
            return $submission->getIdentifier() === $draft->getIdentifier()
                && $submission->getGrantedActions() === AuthorizationService::SUBMISSION_ITEM_ACTIONS;
        }));

        // should work the same for forms with grant-based submission authorization:
        $form2 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission2_1 = $this->addSubmission($form2, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        // don't expect submission2_2 since it's a draft
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));

        // test pagination:
        $this->authorizationService->clearCaches();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(3, $submissionPage1);

        $this->authorizationService->clearCaches();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_3) {
            return $submission->getIdentifier() === $submission1_3->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft) {
            return $submission->getIdentifier() === $draft->getIdentifier()
                && $submission->getGrantedActions() === AuthorizationService::SUBMISSION_ITEM_ACTIONS;
        }));

        // should work the same for forms with grant-based submission authorization:
        $form2 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission2_1 = $this->addSubmission($form2, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $draft2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );
        $draft2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER.'_2'
        );
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        // another user's draft:
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        // current user's own draft 1:
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // current user is shared draft 2:
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        // don't expect submission2_2 since it's another user's draft
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));

        // test pagination:
        $this->authorizationService->clearCaches();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissionPage1);

        $this->authorizationService->clearCaches();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
    }

    public function testGetSubmissionCollectionWithReadFormSubmissionsGrant()
    {
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED);
        $submission1_1 = $this->addSubmission($form, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission1_2 = $this->addSubmission($form, creatorId: self::CURRENT_USER_IDENTIFIER);
        $submission1_3 = $this->addSubmission($form, creatorId: self::ANOTHER_USER_IDENTIFIER.'2');
        $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $draft1 = $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );

        // add some noise:
        $noiseForm = $this->addForm();
        $this->addSubmission($noiseForm);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $submission->getGrantedActions() === AuthorizationService::SUBMISSION_ITEM_ACTIONS;
        }));

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        // don't expect submission4, since it's a draft
        $this->assertCount(4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_3) {
            return $submission->getIdentifier() === $submission1_3->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $submission->getGrantedActions() === AuthorizationService::SUBMISSION_ITEM_ACTIONS;
        }));

        // test pagination:
        $this->authorizationService->clearCaches();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(3, $submissionPage1);

        $this->authorizationService->clearCaches();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_3) {
            return $submission->getIdentifier() === $submission1_3->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $submission->getGrantedActions() === AuthorizationService::SUBMISSION_ITEM_ACTIONS;
        }));

        // should work the same for forms with grant-based submission authorization:
        $form2 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission2_1 = $this->addSubmission($form2, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $draft2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );
        $draft2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER.'_2'
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // current user is shared draft 2:
        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        // don't expect submission2_2 since it's another user's draft
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
               && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        }));

        // test pagination:
        $this->authorizationService->clearCaches();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissionPage1);

        $this->authorizationService->clearCaches();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        }));
    }

    public function testGetSubmissionCollectionGrantBasedSubmissionAuthorization()
    {
        // since only state submitted is allowed and submitted submissions are not readable no submissions are returned
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: []
        );
        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        $submission1 = $this->addSubmission($form);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        // since submitted submissions are not readable only drafts are returned
        $form2 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED | Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []
        );
        $submission2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::CURRENT_USER_IDENTIFIER);
        $submission2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission2_3 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_2->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_3) {
            return $submission->getIdentifier() === $submission2_3->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        // since submitted submissions are readable they are returned
        $form3 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]
        );
        $submission3_1 = $this->addSubmission($form3,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::CURRENT_USER_IDENTIFIER);
        $submission3_2 = $this->addSubmission($form3,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission3_3 = $this->addSubmission($form3,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_2->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));

        // drafts allowed and submissions are readable (and patchable) when submitted -> submissions and drafts are returned
        $form4 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]
        );

        $submission4_1 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::CURRENT_USER_IDENTIFIER);
        $submission4_2 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission4_3 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission4_4 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4_2->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4_3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4_4->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // NOTE: they are granted update and NOT read permission:
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_1) {
            return $submission->getIdentifier() === $submission4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_2) {
            return $submission->getIdentifier() === $submission4_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_4) {
            return $submission->getIdentifier() === $submission4_4->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_2) {
            return $submission->getIdentifier() === $submission4_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_3) {
            return $submission->getIdentifier() === $submission4_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        }));

        // drafts allowed and submissions are readable (and patchable) when submitted -> submissions and drafts are returned
        $form5 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::MANAGE_ACTION, /* add noise: */ AuthorizationService::UPDATE_SUBMISSION_ACTION]
        );

        $submission5_1 = $this->addSubmission($form5,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::CURRENT_USER_IDENTIFIER);
        $submission5_2 = $this->addSubmission($form5,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission5_3 = $this->addSubmission($form5,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission5_4 = $this->addSubmission($form5,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_2->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_4->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // NOTE: no read grant:
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form5->getIdentifier(),
        ]);
        $this->assertCount(4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_1) {
            return $submission->getIdentifier() === $submission5_1->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_2) {
            return $submission->getIdentifier() === $submission5_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::DELETE_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_3) {
            return $submission->getIdentifier() === $submission5_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_4) {
            return $submission->getIdentifier() === $submission5_4->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form5->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_2) {
            return $submission->getIdentifier() === $submission5_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_3) {
            return $submission->getIdentifier() === $submission5_3->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));

        // test pagination:
        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->clearCaches();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 2, [
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(2, $submissionPage1);
        $this->authorizationService->clearCaches();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 2, [
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_1) {
            return $submission->getIdentifier() === $submission4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_2) {
            return $submission->getIdentifier() === $submission4_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_4) {
            return $submission->getIdentifier() === $submission4_4->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
    }

    public function testGetSubmissionCollectionCreatorBasedSubmissionAuthorization()
    {
        $form = $this->addForm(grantBasedSubmissionAuthorization: false);
        $submission = $this->addSubmission($form);

        // some noise: user has grant but submission level authorization is not enabled for this form
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        $form2 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]
        );
        $submission2_1 = $this->addSubmission($form2);
        $submission2_2 = $this->addSubmission($form2);
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));

        // drafts allowed and submissions not readable when submitted -> only drafts are returned
        $form3 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: []
        );
        $submission3_1 = $this->addSubmission($form3, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submission3_2 = $this->addSubmission($form3, submissionState: Submission::SUBMISSION_STATE_SUBMITTED);
        $submission3_3 = $this->addSubmission($form3, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier();
        }));

        // drafts allowed and submissions are readable when submitted -> submissions and drafts are returned
        $form4 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]
        );
        $submission4_1 = $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submission4_2 = $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_SUBMITTED);
        $submission4_3 = $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_1) {
            return $submission->getIdentifier() === $submission4_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_2) {
            return $submission->getIdentifier() === $submission4_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_3) {
            return $submission->getIdentifier() === $submission4_3->getIdentifier();
        }));

        // test pagination:
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 2, [
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(2, $submissionPage1);
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 2, [
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);
        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_1) {
            return $submission->getIdentifier() === $submission4_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_2) {
            return $submission->getIdentifier() === $submission4_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_3) {
            return $submission->getIdentifier() === $submission4_3->getIdentifier();
        }));

        // for service accounts, get submission collection is always empty for creator-based submission authorization,
        // since creatorId is null
        $this->loginServiceAccount();
        $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_SUBMITTED);
        $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);
    }
}
