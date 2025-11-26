<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Dbp\Relay\FormalizeBundle\Rest\FormProvider;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class PostSubmissionControllerTest extends AbstractSubmissionControllerTestCase
{
    public function testAddSubmissionWithCreateFormSubmissionsPermission()
    {
        $form = $this->addForm(grantBasedSubmissionAuthorization: false); // test with available tags
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $tags = [];
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}', tags: $tags);
        $this->assertEquals($submission->getForm()->getIdentifier(), $submission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $submission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submission->getSubmissionState());
        $this->assertEquals($tags, $submission->getTags());
        $this->assertEquals([], $submission->getGrantedActions());

        $gotSubmission = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $gotSubmission->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $gotSubmission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $gotSubmission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $gotSubmission->getSubmissionState());
        $this->assertEquals($tags, $gotSubmission->getTags());
        $this->assertEquals([], $gotSubmission->getGrantedActions());

        $this->authorizationService->reset();

        $form = $this->addForm(grantBasedSubmissionAuthorization: true);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}');
        $this->assertEquals($submission->getForm()->getIdentifier(), $submission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $submission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submission->getSubmissionState());
        $this->assertEquals([], $submission->getTags());
        $this->assertEquals([], $submission->getGrantedActions());

        $gotSubmission = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $gotSubmission->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $gotSubmission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $gotSubmission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $gotSubmission->getSubmissionState());
        $this->assertEquals([], $gotSubmission->getTags());
        $this->assertEquals([], $gotSubmission->getGrantedActions());
    }

    public function testAddSubmissionWithManageFormPermissions(): void
    {
        $form = $this->addForm(grantBasedSubmissionAuthorization: false); // test with NO available tags
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $tags = [AbstractTestCase::TEST_AVAILABLE_TAGS[0]['identifier']];
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}', tags: $tags);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $submission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $submission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submission->getSubmissionState());
        $this->assertEquals($tags, $submission->getTags());
        $this->assertIsPermutationOf([...AuthorizationService::SUBMISSION_ITEM_ACTIONS, ...AuthorizationService::TAG_ACTIONS],
            $submission->getGrantedActions());

        $form = $this->addForm(grantBasedSubmissionAuthorization: true);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}');
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $submission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $submission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submission->getSubmissionState());
        $this->assertEquals([], $submission->getTags());
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, ...AuthorizationService::TAG_ACTIONS],
            $submission->getGrantedActions());
    }

    public function testAddSubmissionDraft()
    {
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}', Submission::SUBMISSION_STATE_DRAFT);
        $this->assertTrue(Uuid::isValid($submission->getIdentifier()));
        $this->assertEquals('{"foo": "bar"}', $submission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_DRAFT, $submission->getSubmissionState());
        $this->assertIsPermutationOf([...AuthorizationService::SUBMISSION_ITEM_ACTIONS, AuthorizationService::READ_TAGS_ACTION], $submission->getGrantedActions());

        $gotSubmission = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $gotSubmission->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $gotSubmission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $gotSubmission->getDataFeedElement());
        $this->assertEquals($submission->getSubmissionState(), $gotSubmission->getSubmissionState());
        $this->assertIsPermutationOf($submission->getGrantedActions(), $gotSubmission->getGrantedActions());

        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}', Submission::SUBMISSION_STATE_DRAFT);
        $this->assertTrue(Uuid::isValid($submission->getIdentifier()));
        $this->assertEquals('{"foo": "bar"}', $submission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_DRAFT, $submission->getSubmissionState());
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, AuthorizationService::READ_TAGS_ACTION], $submission->getGrantedActions());

        $gotSubmission = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $gotSubmission->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $gotSubmission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $gotSubmission->getDataFeedElement());
        $this->assertEquals($submission->getSubmissionState(), $gotSubmission->getSubmissionState());
        $this->assertIsPermutationOf($submission->getGrantedActions(), $gotSubmission->getGrantedActions());
    }

    public function testAddSubmissionWithAllowedActionsWhenSubmitted()
    {
        $form = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}');
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertIsPermutationOf(
            [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION, AuthorizationService::READ_TAGS_ACTION],
            $submission->getGrantedActions());

        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::DELETE_SUBMISSION_ACTION]
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}');
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertEquals([AuthorizationService::DELETE_SUBMISSION_ACTION], $submission->getGrantedActions());

        $form = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::MANAGE_ACTION]
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}');
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertIsPermutationOf([AuthorizationService::MANAGE_ACTION, AuthorizationService::READ_TAGS_ACTION], $submission->getGrantedActions());
    }

    public function testAddSubmissionWithoutPermission()
    {
        $form = $this->addForm();

        try {
            $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}');
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testAddSubmissionDeprecateJsonLd()
    {
        /** @var FormProvider $formProvider */
        $formProvider = static::getContainer()->get(FormProvider::class);
        TestAuthorizationService::setUp($formProvider, self::CURRENT_USER_IDENTIFIER);
        /** @var \Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService $authorizationAuthorizationService */
        $authorizationAuthorizationService = static::getContainer()->get(
            \Dbp\Relay\AuthorizationBundle\Authorization\AuthorizationService::class);
        TestAuthorizationService::setUp($authorizationAuthorizationService, self::CURRENT_USER_IDENTIFIER);

        $form = $this->addForm();
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}', testDeprecateJsonLd: true);

        $gotSubmission = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $gotSubmission->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $gotSubmission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $gotSubmission->getDataFeedElement());
    }

    public function testAddSubmissionWithFiles()
    {
        $form = $this->addForm();
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}',
            files: [
                'cv' => self::createUploadedTestFile(),
                'birthCertificate' => self::createUploadedTestFile(self::PDF_FILE_PATH),
            ]);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());

        $gotSubmission = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $gotSubmission->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $gotSubmission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $gotSubmission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $gotSubmission->getSubmissionState());
        $this->assertCount(2, $submission->getSubmittedFiles());
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->toArray(),
            function (SubmittedFile $submittedFile) {
                return $submittedFile->getFileAttributeName() === 'birthCertificate'
                    && Uuid::isValid($submittedFile->getIdentifier())
                    && $submittedFile->getFileName() === self::PDF_FILE_NAME
                    && $submittedFile->getFileSize() === filesize(self::PDF_FILE_PATH)
                    && $submittedFile->getMimeType() === 'application/pdf';
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->toArray(),
            function (SubmittedFile $submittedFile) {
                return $submittedFile->getFileAttributeName() === 'cv'
                    && Uuid::isValid($submittedFile->getIdentifier())
                    && $submittedFile->getFileName() === self::TEXT_FILE_NAME
                    && $submittedFile->getFileSize() === filesize(self::TEXT_FILE_PATH)
                    && $submittedFile->getMimeType() === 'text/plain';
            }));
    }

    public function testAddSubmissionDraftWithFiles()
    {
        $form = $this->addForm(allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationService->reset();
        $submission = $this->postSubmission($form->getIdentifier(), '{"foo": "bar"}',
            Submission::SUBMISSION_STATE_DRAFT,
            files: [
                'texts' => [self::createUploadedTestFile(), self::createUploadedTestFile(self::TEXT_FILE_2_PATH)],
            ]);
        $this->assertEquals($submission->getIdentifier(), $this->getSubmission($submission->getIdentifier())->getIdentifier());

        $gotSubmission = $this->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getIdentifier(), $gotSubmission->getIdentifier());
        $this->assertEquals($submission->getForm()->getIdentifier(), $gotSubmission->getForm()->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $gotSubmission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_DRAFT, $gotSubmission->getSubmissionState());
        $this->assertCount(2, $submission->getSubmittedFiles());
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->toArray(),
            function (SubmittedFile $submittedFile) {
                return $submittedFile->getFileAttributeName() === 'texts'
                    && Uuid::isValid($submittedFile->getIdentifier())
                    && $submittedFile->getFileName() === self::TEXT_FILE_2_NAME
                    && $submittedFile->getFileSize() === filesize(self::TEXT_FILE_2_PATH)
                    && $submittedFile->getMimeType() === 'text/plain';
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->toArray(),
            function (SubmittedFile $submittedFile) {
                return $submittedFile->getFileAttributeName() === 'texts'
                    && Uuid::isValid($submittedFile->getIdentifier())
                    && $submittedFile->getFileName() === self::TEXT_FILE_NAME
                    && $submittedFile->getFileSize() === filesize(self::TEXT_FILE_PATH)
                    && $submittedFile->getMimeType() === 'text/plain';
            }));
    }
}
