<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class PatchSubmissionControllerTestCase extends AbstractSubmissionControllerTestCase
{
    public function testPatchSubmissionWithManageFormPermission()
    {
        $form = $this->addForm();
        $dataFeedElement = json_encode(['firstName' => 'John']);
        $submission = $this->addSubmission($form, $dataFeedElement, creatorId: self::ANOTHER_USER_IDENTIFIER);

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($dataFeedElement, $submissionPersistence->getDataFeedElement());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $dataFeedElement = json_encode(['firstName' => 'Joni']);
        $tags = ['tag1', 'tag2'];
        $submissionUpdated = $this->patchSubmission($submission->getIdentifier(), $dataFeedElement);

        $this->assertEquals($dataFeedElement, $this->getSubmission($submission->getIdentifier())->getDataFeedElement());
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submissionUpdated->getGrantedActions());

        $this->testEntityManager->updateForm($form, actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]);

        $this->authorizationService->clearCaches();
        $submissionUpdated = $this->patchSubmission($submission->getIdentifier(), $dataFeedElement);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testPatchSubmissionWithUpdateFormSubmissionsPermission()
    {
        $form = $this->addForm();
        $dataFeedElement = json_encode(['firstName' => 'John']);
        $submission = $this->addSubmission($form, $dataFeedElement, creatorId: self::ANOTHER_USER_IDENTIFIER);

        $submissionPersistence = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($dataFeedElement, $submissionPersistence->getDataFeedElement());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $dataFeedElement = json_encode(['firstName' => 'Joni']);
        $submissionUpdated = $this->patchSubmission($submission->getIdentifier(), $dataFeedElement);

        $this->assertEquals($dataFeedElement, $this->getSubmission($submission->getIdentifier())->getDataFeedElement());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());

        $this->testEntityManager->updateForm($form, actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]);

        $submissionUpdated = $this->patchSubmission($submission->getIdentifier(), $dataFeedElement);
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testPatchSubmissionDraftWithUpdateFormSubmissionsPermission()
    {
        $form = $this->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $submission = $this->addSubmission($form,
            submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->login(self::CURRENT_USER_IDENTIFIER);
            $this->patchSubmission($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testPatchSubmissionGrantBasedAuthorization()
    {
        // user has a grant to read a submission of a form (with grant-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: [AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionUpdated = $this->patchSubmission($submission->getIdentifier());

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->authorizationService->clearCaches();
        $submissionUpdated = $this->patchSubmission($submission->getIdentifier());

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testPatchSubmissionGrantBasedAuthorizationUpdateNotAllowedWhenSubmitted()
    {
        // user has a grant to update a submission of a form (with grant-based submission authorization),
        // however, update is not allowed when the submission is in submitted state
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->patchSubmission($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testPatchSubmissionDraftGrantBasedAuthorization()
    {
        // user has a grant to update a submission of a form (with grant-based submission authorization),
        // however update is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
            AuthorizationService::UPDATE_SUBMISSION_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submissionUpdated = $this->patchSubmission($submission->getIdentifier());

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testPatchSubmissionCreatorBasedAuthorization()
    {
        // user may update their own submission to a form (with creator-based submission authorization)
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            actionsAllowedWhenSubmitted: [AuthorizationService::UPDATE_SUBMISSION_ACTION]);
        $submission = $this->addSubmission($form, creatorId: self::CURRENT_USER_IDENTIFIER);

        $submissionUpdated = $this->patchSubmission($submission->getIdentifier());

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals([AuthorizationService::UPDATE_SUBMISSION_ACTION], $submissionUpdated->getGrantedActions());
    }

    public function testPatchSubmissionCreatorBasedAuthorizationUpdateNotAllowedWhenSubmitted()
    {
        // user may update their own submission to a form (with creator-based submission authorization),
        // however, update is not allowed when the submission is in submitted state
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            actionsAllowedWhenSubmitted: []);
        $submission = $this->addSubmission($form, creatorId: self::CURRENT_USER_IDENTIFIER);

        try {
            $this->patchSubmission($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testPatchSubmissionDraftCreatorBasedAuthorization()
    {
        // user may update their own submission to a form (with creator-based submission authorization),
        // however, update is not allowed when the submission is in submitted state,
        // but for drafts it ok
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT,
            actionsAllowedWhenSubmitted: []);

        $submission = $this->addSubmission($form, submissionState: Submission::SUBMISSION_STATE_DRAFT,
            creatorId: self::CURRENT_USER_IDENTIFIER);

        $submissionUpdated = $this->patchSubmission($submission->getIdentifier());

        $this->assertEquals($submission->getIdentifier(), $submissionUpdated->getIdentifier());
        $this->assertEquals(AuthorizationService::SUBMISSION_ITEM_ACTIONS, $submissionUpdated->getGrantedActions());
    }

    public function testPatchSubmissionWithoutPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form, '{"firstName" : "John"}',
            creatorId: self::ANOTHER_USER_IDENTIFIER);

        try {
            $this->patchSubmission($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testPatchSubmissionWrongPermissions()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form, '{"firstName" : "John"}',
            creatorId: self::ANOTHER_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->patchSubmission($submission->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testPatchSubmissionWithFiles()
    {
        // user may update their own submission to a form (with creator-based submission authorization)
        $form = $this->addForm(
            actionsAllowedWhenSubmitted: [AuthorizationService::UPDATE_SUBMISSION_ACTION]);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $submission = $this->postSubmission($form->getIdentifier(), '{"firstName" : "John"}',
            files: [
                'cv' => self::createUploadedTestFile(),
                'birthCertificate' => self::createUploadedTestFile(self::PDF_FILE_PATH),
            ]);

        $this->assertCount(2, $submission->getSubmittedFiles());
        /** @var SubmittedFile $submittedCV */
        $submittedCV = $this->selectWhere($submission->getSubmittedFiles()->toArray(),
            function (SubmittedFile $submittedFile) {
                return $submittedFile->getFileAttributeName() === 'cv'
                    && Uuid::isValid($submittedFile->getIdentifier())
                    && $submittedFile->getFileName() === self::TEXT_FILE_NAME
                    && $submittedFile->getFileSize() === filesize(self::TEXT_FILE_PATH)
                    && $submittedFile->getMimeType() === 'text/plain';
            });
        $this->assertCount(1, $submittedCV);

        $updatedSubmission = $this->patchSubmission($submission->getIdentifier(),
            '{"firstName" : "Joni"}',
            files: [
                'cv' => self::createUploadedTestFile(self::TEXT_FILE_2_PATH),
            ], filesToDelete: [$submittedCV[0]->getIdentifier()]);

        $this->assertCount(1, $updatedSubmission->getSubmittedFiles());
        /** @var SubmittedFile $submittedCV */
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->toArray(),
            function (SubmittedFile $submittedFile) {
                return $submittedFile->getFileAttributeName() === 'cv'
                    && Uuid::isValid($submittedFile->getIdentifier())
                    && $submittedFile->getFileName() === self::TEXT_FILE_2_NAME
                    && $submittedFile->getFileSize() === filesize(self::TEXT_FILE_2_PATH)
                    && $submittedFile->getMimeType() === 'text/plain';
            }));
    }
}
