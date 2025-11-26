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

    public function testRemoveSubmissionWithManageFormGrant()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
    }

    public function testRemoveSubmissionWithDeleteFormSubmissionsGrant()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

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
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

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
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->submissionProcessorTester->removeItem($submission->getIdentifier(), $submission);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
