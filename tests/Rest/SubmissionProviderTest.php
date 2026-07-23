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

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

        $this->assertEquals($submission->getIdentifier(), $this->submissionProviderTester->getItem($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submission->getGrantedActions());
    }

    public function testGetSubmissionItemWithReadFormSubmissionsPermission()
    {
        // user has a grant to read all submissions of a form
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertIsPermutationOf([AuthorizationService::READ_SUBMISSION_ACTION], $submissionPersistence->getGrantedActions());

        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertIsPermutationOf([
            AuthorizationService::READ_SUBMISSION_ACTION,
            AuthorizationService::UPDATE_SUBMISSION_ACTION,
        ], $submissionPersistence->getGrantedActions());
    }

    public function testGetSubmissionItemWithMissingReadFormSubmissionsPermission()
    {
        // user has a grant to read all submissions of a form
        $form = $this->addForm(
            roleIdentifierWhenSubmitted: 'null'
        );
        $submission = $this->addSubmission($form);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

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

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

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
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionPersistence = $this->submissionProviderTester->getItem($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertIsPermutationOf(
            [AuthorizationService::READ_SUBMISSION_ACTION],
            $submissionPersistence->getGrantedActions());
    }

    public function testGetSubmissionItemDraft()
    {
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            roleIdentifierWhenDraft: AuthorizationService::EDITOR_WITH_DELETE_ROLE_IDENTIFIER, // default
            roleIdentifierWhenSubmitted: 'null'
        );

        $draft = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);

        $submissionPersistence = $this->submissionProviderTester->getItem($draft->getIdentifier());
        $this->assertEquals($draft->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertIsPermutationOf([
            AuthorizationService::READ_SUBMISSION_ACTION,
            AuthorizationService::UPDATE_SUBMISSION_ACTION,
            AuthorizationService::DELETE_SUBMISSION_ACTION,
        ], $submissionPersistence->getGrantedActions());
    }

    public function testGetSubmissionItemWithoutPermissions()
    {
        $form = $this->addForm(
            roleIdentifierWhenSubmitted: 'null' // form submissions are not readable by submitters once submitted
        );
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
        $form = $this->addForm(
            roleIdentifierWhenSubmitted: 'null' // form submissions are not readable by submitters once submitted
        );
        $submission = $this->addSubmission($form);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

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
        $draft1 = $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );

        // add some noise:
        $noiseForm = $this->addForm();
        $this->addSubmission($noiseForm);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions,
            function (Submission $submission) use ($draft1) {
                return $submission->getIdentifier() === $draft1->getIdentifier()
                    && $this->isPermutationOf([
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                        AuthorizationService::DELETE_SUBMISSION_ACTION,
                    ], $submission->getGrantedActions());
            }));
        $this->assertCount(1, $this->selectWhere($submissions,
            function (Submission $submission) use ($submission1_2) {
                return $submission->getIdentifier() === $submission1_2->getIdentifier()
                    && $submission->getGrantedActions() === [AuthorizationService::READ_SUBMISSION_ACTION];
            }));

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

        // add some noise: grants should have no effect on creator-based authorization
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1_1->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER
        );

        $this->authorizationService->reset();
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
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));
        $this->authorizationService->reset();

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $submission->getGrantedActions() === [AuthorizationService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));

        // should work the same for forms with grant-based submission authorization:
        $form2 = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

        $submission2_1 = $this->addSubmission($form2, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $draft_2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft_2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        // don't expect submission2_2 since it's a draft
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(0, $submissions);

        // test pagination:
        $this->authorizationService->reset();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(3, $submissionPage1);

        $this->authorizationService->reset();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
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
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));
        $this->authorizationService->reset();

        // should work the same for forms with grant-based submission authorization:
        $form2 = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

        $submission2_1 = $this->addSubmission($form2, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $draft_2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $draft2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );
        $draft2_3 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER.'_2'
        );
        // another user's draft:
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft_2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        // current user's draft:
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // another user 2's draft :
        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER.'_2');
        // current user is shared draft 3:
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        // don't expect submission2_2 since it's another user's draft
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_3) {
            return $submission->getIdentifier() === $draft2_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        // test pagination:
        $this->authorizationService->reset();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissionPage1);

        $this->authorizationService->reset();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_3) {
            return $submission->getIdentifier() === $draft2_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
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

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        // don't expect submission4, since it's a draft
        $this->assertCount(4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_3) {
            return $submission->getIdentifier() === $submission1_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));

        // test pagination:
        $this->authorizationService->reset();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(3, $submissionPage1);

        $this->authorizationService->reset();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 3, [
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_1) {
            return $submission->getIdentifier() === $submission1_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_2) {
            return $submission->getIdentifier() === $submission1_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1_3) {
            return $submission->getIdentifier() === $submission1_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft1) {
            return $submission->getIdentifier() === $draft1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));

        // form with grant-based submission authorization:
        $form2 = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            roleIdentifierWhenSubmitted: AuthorizationService::EDITOR_WITHOUT_DELETE_ROLE_IDENTIFIER,
        );
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER,
            isResourceGroup: true
        );

        $submission2_1 = $this->addSubmission($form2, creatorId: self::ANOTHER_USER_IDENTIFIER);
        $draft2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $draft2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );
        $draft2_3 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER.'_2'
        );

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // current user is shared draft 2:
        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_3->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        // don't expect draft2_1 since it's another user's draft
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_3) {
            return $submission->getIdentifier() === $draft2_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->authorizationService->reset();

        // additionally, add an update grant for submission2_1 for current user, which has form-level read submissions
        // -> the granted actions should be a combination of form level "read" and submission level "update"
        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        // don't expect draft2_1 since it's another user's draft
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_3) {
            return $submission->getIdentifier() === $draft2_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));

        // test pagination:
        $this->authorizationService->reset();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissionPage1);

        $this->authorizationService->reset();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 2, [
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::MANAGE_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_3) {
            return $submission->getIdentifier() === $draft2_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
    }

    public function testGetSubmissionCollectionGrantBasedSubmissionAuthorization()
    {
        // since only state submitted is allowed and submitted submissions are not readable, no submissions are returned
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            roleIdentifierWhenSubmitted: 'null'
        );
        $submission1 = $this->addSubmission($form);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form->getIdentifier(),
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission1) {
            return $submission->getIdentifier() === $submission1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));

        // since submitted submissions are not readable, only drafts are returned
        $form2 = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED | Submission::SUBMISSION_STATE_DRAFT,
            roleIdentifierWhenSubmitted: 'null'
        );
        $submission2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::CURRENT_USER_IDENTIFIER
        );
        $draft2_1 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );
        $draft2_2 = $this->addSubmission($form2,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER
        );

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission2_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft2_1->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
            'creatorIdEquals' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
            'creatorIdEquals' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_1) {
            return $submission->getIdentifier() === $draft2_1->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft2_2) {
            return $submission->getIdentifier() === $draft2_2->getIdentifier()
                && $this->isPermutationOf([
                    AuthorizationService::READ_SUBMISSION_ACTION,
                    AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    AuthorizationService::DELETE_SUBMISSION_ACTION,
                ], $submission->getGrantedActions());
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER.'_2');

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form2->getIdentifier(),
        ]);
        $this->assertCount(0, $submissions);

        // since submitted submissions are readable, they are returned
        $form3 = $this->addForm();
        $submission3_1 = $this->addSubmission($form3,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::CURRENT_USER_IDENTIFIER);
        $submission3_2 = $this->addSubmission($form3,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission3_3 = $this->addSubmission($form3,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::ANOTHER_USER_IDENTIFIER);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_1->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission3_3->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_1) {
            return $submission->getIdentifier() === $submission3_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
            'creatorIdEquals' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(0, $submissions);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form3->getIdentifier(),
            'creatorIdEquals' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_2) {
            return $submission->getIdentifier() === $submission3_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission3_3) {
            return $submission->getIdentifier() === $submission3_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        // drafts allowed and submissions are readable (and patchable) when submitted -> submissions and drafts are returned
        $form4 = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            roleIdentifierWhenSubmitted: AuthorizationService::EDITOR_WITHOUT_DELETE_ROLE_IDENTIFIER
        );

        $submission4_1 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::CURRENT_USER_IDENTIFIER);
        $draft4_1 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $submission4_2 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_SUBMITTED,
            creatorId: self::ANOTHER_USER_IDENTIFIER);
        $draft4_2 = $this->addSubmission($form4,
            submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4_1->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft4_1->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission4_2->getIdentifier(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $draft4_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // NOTE: they are granted update and NOT read permission:
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_1) {
            return $submission->getIdentifier() === $submission4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_1) {
            return $submission->getIdentifier() === $draft4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_2) {
            return $submission->getIdentifier() === $draft4_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_1) {
            return $submission->getIdentifier() === $submission4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_2) {
            return $submission->getIdentifier() === $draft4_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
            'creatorIdEquals' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_1) {
            return $submission->getIdentifier() === $draft4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_1) {
            return $submission->getIdentifier() === $draft4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_2) {
            return $submission->getIdentifier() === $submission4_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
            'creatorIdEquals' => self::CURRENT_USER_IDENTIFIER,
        ]);
        $this->assertCount(0, $submissions);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form4->getIdentifier(),
            'creatorIdEquals' => self::ANOTHER_USER_IDENTIFIER,
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_1) {
            return $submission->getIdentifier() === $draft4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_2) {
            return $submission->getIdentifier() === $submission4_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));

        // drafts allowed and submissions are readable (and patchable) when submitted -> submissions and drafts are returned
        $form5 = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            roleIdentifierWhenSubmitted: ResourceActionGrantService::MANAGER_ROLE_IDENTIFIER,
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

        $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_2->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $rag = $this->addResourceActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_4->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        // NOTE: no read grant:
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form5->getIdentifier(),
        ]);
        $this->assertCount(4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_1) {
            return $submission->getIdentifier() === $submission5_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_2) {
            return $submission->getIdentifier() === $submission5_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::DELETE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_3) {
            return $submission->getIdentifier() === $submission5_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_4) {
            return $submission->getIdentifier() === $submission5_4->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));

        $this->login(self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissions = $this->submissionProviderTester->getCollection([
            'formIdentifier' => $form5->getIdentifier(),
        ]);
        $this->assertCount(2, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_2) {
            return $submission->getIdentifier() === $submission5_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission5_3) {
            return $submission->getIdentifier() === $submission5_3->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));

        // test pagination:
        $this->login(self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submissionPage1 = $this->submissionProviderTester->getPage(1, 2, [
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(2, $submissionPage1);
        $this->authorizationService->reset();
        $submissionPage2 = $this->submissionProviderTester->getPage(2, 2, [
            'formIdentifier' => $form4->getIdentifier(),
        ]);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($submission4_1) {
            return $submission->getIdentifier() === $submission4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [
                        AuthorizationService::READ_SUBMISSION_ACTION,
                        AuthorizationService::UPDATE_SUBMISSION_ACTION,
                    ]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_1) {
            return $submission->getIdentifier() === $draft4_1->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::READ_SUBMISSION_ACTION]);
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function (Submission $submission) use ($draft4_2) {
            return $submission->getIdentifier() === $draft4_2->getIdentifier()
                && $this->isPermutationOf($submission->getGrantedActions(),
                    [AuthorizationService::MANAGE_ACTION]);
        }));
    }
}
