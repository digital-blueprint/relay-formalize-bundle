<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProcessor;
use Symfony\Component\HttpFoundation\Response;

class PatchSubmissionControllerTest extends RestTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $submissionProcessor = new SubmissionProcessor($this->formalizeService, $this->authorizationService);
        $this->submissionProcessorTester = DataProcessorTester::create(
            $submissionProcessor, Submission::class, ['FormalizeSubmission:input']);
    }

    public function testAddSubmissionWithCreateFormSubmissionsPermission()
    {
        $form = $this->addForm(grantBasedSubmissionAuthorization: false);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([], $submission->getGrantedActions());

        $form = $this->addForm(grantBasedSubmissionAuthorization: true);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([], $submission->getGrantedActions());
    }

    public function testAddSubmissionWithManageFormPermissions(): void
    {
        $form = $this->addForm(grantBasedSubmissionAuthorization: false);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submission->getGrantedActions());

        $form = $this->addForm(grantBasedSubmissionAuthorization: true);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submission->getGrantedActions());
    }

    public function testAddSubmissionDraft()
    {
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_DRAFT);

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertIsPermutationOf(AuthorizationService::SUBMISSION_ITEM_ACTIONS, $submission->getGrantedActions());

        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_DRAFT);

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submission->getGrantedActions());
    }

    public function testAddSubmissionWithAllowedActionsWhenSubmitted()
    {
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertIsPermutationOf(
            [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION],
            $submission->getGrantedActions());

        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::DELETE_SUBMISSION_ACTION]
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::DELETE_SUBMISSION_ACTION], $submission->getGrantedActions());

        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::MANAGE_ACTION]
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        $this->authorizationService->clearCaches();
        $this->submissionProcessorTester->addItem($submission);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submission->getGrantedActions());
    }

    public function testAddSubmissionWithoutPermission()
    {
        $form = $this->addForm();

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"foo": "bar"}');

        try {
            $this->submissionProcessorTester->addItem($submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateSubmissionWithManageFormPermission()
    {
        $form = $this->addForm();
        $dataFeedElement = json_encode(['firstName' => 'John']);
        $submission = $this->addSubmission($form, $dataFeedElement);

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($dataFeedElement, $submissionPersistence->getDataFeedElement());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $dataFeedElement = json_encode(['firstName' => 'Joni']);
        $submissionPersistence->setDataFeedElement($dataFeedElement);

        $submissionUpdated = $this->submissionProcessorTester->updateItem($submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals($dataFeedElement, $this->getSubmission($submission->getIdentifier())->getDataFeedElement());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submissionUpdated->getGrantedActions());

        $this->testEntityManager->updateForm($form, actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]);

        $this->authorizationService->clearCaches();
        $submissionUpdated = $this->submissionProcessorTester->updateItem($submission->getIdentifier(), $submissionPersistence, $submission);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testUpdateSubmissionWithUpdateFormSubmissionsPermission()
    {
        $form = $this->addForm();
        $dataFeedElement = json_encode(['firstName' => 'John']);
        $submission = $this->addSubmission($form, $dataFeedElement);

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($dataFeedElement, $submissionPersistence->getDataFeedElement());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $dataFeedElement = json_encode(['firstName' => 'Joni']);
        $submissionPersistence->setDataFeedElement($dataFeedElement);

        $submissionUpdated = $this->submissionProcessorTester->updateItem($submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals($dataFeedElement, $this->getSubmission($submission->getIdentifier())->getDataFeedElement());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());

        $this->testEntityManager->updateForm($form, actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]);

        $submissionUpdated = $this->submissionProcessorTester->updateItem($submission->getIdentifier(), $submissionPersistence, $submission);
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testUpdateSubmissionDraftWithUpdateFormSubmissionsPermission()
    {
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $submission = $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->login(self::CURRENT_USER_IDENTIFIER);
            $this->submissionProcessorTester->updateItem($submission->getIdentifier(),
                $this->getSubmission($submission->getIdentifier()), $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateSubmissionGrantBasedAuthorization()
    {
        // user has a grant to read a submission of a form (with grant-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: [AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionUpdated = $this->submissionProcessorTester->updateItem(
            $submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationService->clearCaches();
        $submissionUpdated = $this->submissionProcessorTester->updateItem(
            $submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testUpdateSubmissionGrantBasedAuthorizationUpdateNotAllowedWhenSubmitted()
    {
        // user has a grant to update a submission of a form (with grant-based submission authorization),
        // however, update is not allowed when the submission is in submitted state
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProcessorTester->updateItem(
                $submission->getIdentifier(), $submissionPersistence, $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateSubmissionDraftGrantBasedAuthorization()
    {
        // user has a grant to update a submission of a form (with grant-based submission authorization),
        // however update is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionUpdated = $this->submissionProcessorTester->updateItem(
            $submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testUpdateSubmissionCreatorBasedAuthorization()
    {
        // user may update their own submission to a form (with creator-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            actionsAllowedWhenSubmitted: [AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $submissionUpdated = $this->submissionProcessorTester->updateItem(
            $submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testUpdateSubmissionCreatorBasedAuthorizationUpdateNotAllowedWhenSubmitted()
    {
        // user may update their own submission to a form (with creator-based submission authorization),
        // however, update is not allowed when the submission is in submitted state
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        try {
            $this->submissionProcessorTester->updateItem(
                $submission->getIdentifier(), $submissionPersistence, $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateSubmissionDraftCreatorBasedAuthorization()
    {
        // user may update their own submission to a form (with creator-based submission authorization),
        // however, update is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $submissionUpdated = $this->submissionProcessorTester->updateItem(
            $submission->getIdentifier(), $submissionPersistence, $submission);

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals(AuthorizationService::SUBMISSION_ITEM_ACTIONS, $submissionUpdated->getGrantedActions());
    }

    public function testUpdateSubmissionWithoutPermissions()
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

    public function testUpdateSubmissionWrongPermissions()
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

    public function testRemoveSubmissionWithManageFormGrant()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
    }

    public function testRemoveSubmissionWithDeleteFormSubmissionsGrant()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
    }

    public function testRemoveSubmissionDraftWithUpdateFormSubmissionsPermission()
    {
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $submission = $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->login(self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProcessorTester->removeItem($submission->getIdentifier(),
                $this->getSubmission($submission->getIdentifier()));
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveSubmissionGrantBasedAuthorization()
    {
        // user has a grant to read a submission of a form (with grant-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: [AuthorizationService::DELETE_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->submissionProcessorTester->removeItem(
            $submission->getIdentifier(), $submissionPersistence);

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
    }

    public function testRemoveSubmissionGrantBasedAuthorizationRemoveNotAllowedWhenSubmitted()
    {
        // user has a grant to update a submission of a form (with grant-based submission authorization),
        // however, update is not allowed when the submission is in submitted state
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProcessorTester->removeItem(
                $submission->getIdentifier(), $submissionPersistence);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveSubmissionDraftGrantBasedAuthorization()
    {
        // user has a grant to update a submission of a form (with grant-based submission authorization),
        // however update is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->submissionProcessorTester->removeItem(
            $submission->getIdentifier(), $submissionPersistence);

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
    }

    public function testRemoveSubmissionCreatorBasedAuthorization()
    {
        // user may update their own submission to a form (with creator-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            actionsAllowedWhenSubmitted: [AuthorizationService::DELETE_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->submissionProcessorTester->removeItem(
            $submission->getIdentifier(), $submissionPersistence);

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
    }

    public function testRemoveSubmissionCreatorBasedAuthorizationRemoveNotAllowedWhenSubmitted()
    {
        // user may update their own submission to a form (with creator-based submission authorization),
        // however, update is not allowed when the submission is in submitted state
        $form = $this->addForm(grantBasedSubmissionAuthorization: false, actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        try {
            $this->submissionProcessorTester->removeItem(
                $submission->getIdentifier(), $submissionPersistence);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveSubmissionDraftCreatorBasedAuthorization()
    {
        // user may update their own submission to a form (with creator-based submission authorization),
        // however, update is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $submissionPersistence = $this->getSubmission($submission->getIdentifier());

        $this->submissionProcessorTester->removeItem(
            $submission->getIdentifier(), $submissionPersistence);

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
