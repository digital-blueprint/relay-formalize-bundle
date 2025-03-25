<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\BlobBundle\Api\FileApiException;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class FormalizeServiceTest extends AbstractTestCase
{
    public function testAddFormDefaults()
    {
        $form = new Form();
        $form->setName(self::TEST_FORM_NAME);
        $form = $this->formalizeService->addForm($form);

        $this->assertNotEmpty($form->getIdentifier());
        $this->assertNotEmpty($form->getDateCreated());
        $this->assertSame(self::TEST_FORM_NAME, $form->getName());
        $this->assertSame(self::CURRENT_USER_IDENTIFIER, $form->getCreatorId());
        $this->assertSame(Submission::SUBMISSION_STATE_SUBMITTED, $form->getAllowedSubmissionStates());
        $this->assertEmpty($form->getAllowedActionsWhenSubmitted());
        $this->assertEquals(10, $form->getMaxNumSubmissionsPerCreator());
        $this->assertNull($form->getDataFeedSchema());

        $formPersistence = $this->testEntityManager->getForm($form->getIdentifier());
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());
        $this->assertSame($form->getAllowedSubmissionStates(), $formPersistence->getAllowedSubmissionStates());
        $this->assertSame($form->getAllowedActionsWhenSubmitted(), $formPersistence->getAllowedActionsWhenSubmitted());
        $this->assertSame($form->getMaxNumSubmissionsPerCreator(), $formPersistence->getMaxNumSubmissionsPerCreator());
        $this->assertNull($formPersistence->getDataFeedSchema());
    }

    public function testAddForm()
    {
        $allowedSubmissionStates = Submission::SUBMISSION_STATE_SUBMITTED | Submission::SUBMISSION_STATE_DRAFT;

        $form = new Form();
        $form->setName(self::TEST_FORM_NAME);
        $form->setDataFeedSchema(self::TEST_FORM_SCHEMA);
        $form->setAllowedSubmissionStates($allowedSubmissionStates);
        $form = $this->formalizeService->addForm($form);

        $this->assertEquals(self::TEST_FORM_SCHEMA, $form->getDataFeedSchema());
        $this->assertSame($allowedSubmissionStates, $form->getAllowedSubmissionStates());

        $formPersistence = $this->testEntityManager->getForm($form->getIdentifier());
        $this->assertSame($form->getDataFeedSchema(), $formPersistence->getDataFeedSchema());
        $this->assertSame($form->getAllowedSubmissionStates(), $formPersistence->getAllowedSubmissionStates());
    }

    /**
     * @throws \JsonException
     */
    public function testAddFormNameMissingError()
    {
        try {
            $form = new Form();
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('field \'name\' is required', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-field-missing', $apiError->getErrorId());
        }
    }

    /**
     * @throws \JsonException
     */
    public function testAddFormDataFeedErrorInvalidJson()
    {
        // invalid JSON
        $testDataFeedSchema = '{
            "type" = "object"
        }';

        $form = new Form();
        $form->setName(self::TEST_FORM_NAME);
        $form->setDataFeedSchema($testDataFeedSchema);

        try {
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('\'dataFeedSchema\' is not valid JSON', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-invalid-data-feed-schema', $apiError->getErrorId());
        }
    }

    /**
     * @throws \JsonException
     */
    public function testAddFormDataFeedErrorInvalidJsonSchema()
    {
        // invalid JSON schema
        $testDataFeedSchema = '{
            "type": "foo"
        }';

        $form = new Form();
        $form->setName(self::TEST_FORM_NAME);
        $form->setDataFeedSchema($testDataFeedSchema);

        try {
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('\'dataFeedSchema\' is not a valid JSON schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-invalid-data-feed-schema', $apiError->getErrorId());
        }
    }

    public function testUpdateForm()
    {
        $form = $this->testEntityManager->addForm(self::TEST_FORM_NAME);
        $formId = $form->getIdentifier();
        $dataCreated = $form->getDateCreated();

        $form->setName('Updated Name');
        $this->formalizeService->updateForm($form);

        $formPersistence = $this->testEntityManager->getForm($form->getIdentifier());
        $this->assertSame($formId, $formPersistence->getIdentifier());
        $this->assertSame('Updated Name', $formPersistence->getName());
        $this->assertSame($dataCreated, $formPersistence->getDateCreated());
    }

    public function testGetForm()
    {
        $form = $this->testEntityManager->addForm(self::TEST_FORM_NAME);
        $this->assertSame(self::TEST_FORM_NAME, $form->getName());

        $formPersistence = $this->formalizeService->getForm($form->getIdentifier());
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());
    }

    public function testGetFormNotFoundError()
    {
        try {
            $this->formalizeService->getForm('notFound');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('Form could not be found', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-with-id-not-found', $apiError->getErrorId());
        }
    }

    /**
     * @throws \JsonException
     */
    public function testRemoveForm()
    {
        $form = $this->testEntityManager->addForm();
        $submission = $this->testEntityManager->addSubmission($form);

        $this->assertNotNull($this->testEntityManager->getForm($form->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getSubmission($submission->getIdentifier()));

        $this->formalizeService->removeForm($form);

        // form and submission must be removed
        $this->assertNull($this->testEntityManager->getForm($form->getIdentifier()));
        $this->assertNull($this->testEntityManager->getSubmission($submission->getIdentifier()));
    }

    public function testAddSubmissionDefaults()
    {
        $form = $this->testEntityManager->addForm();

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setForm($form);

        $submission = $this->formalizeService->addSubmission($submission);
        $this->assertNotNull($submission->getIdentifier());
        $this->assertNotNull($submission->getDateCreated());
        $this->assertSame(self::CURRENT_USER_IDENTIFIER, $submission->getCreatorId());
        $this->assertSame(Submission::SUBMISSION_STATE_SUBMITTED, $submission->getSubmissionState());
        $this->assertEquals($submission->getDateCreated(), $submission->getDateLastModified());

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());
        $this->assertEquals($submission->getDateLastModified(), $submissionPersistence->getDateLastModified());
        $this->assertSame($submission->getCreatorId(), $submissionPersistence->getCreatorId());
        $this->assertSame($submission->getSubmissionState(), $submissionPersistence->getSubmissionState());

        $formPersistence = $submissionPersistence->getForm();
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());

        $this->assertTrue($this->testSubmissionEventSubscriber->wasCreateSubmissionPostEventCalled());
    }

    public function testAddSubmissionDraft()
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED | Submission::SUBMISSION_STATE_DRAFT);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_DRAFT);

        // expecting no complaint about the missing data, since it's only a draft
        $submission = $this->formalizeService->addSubmission($submission);
        $this->assertNull($submission->getDataFeedElement());
        $this->assertSame(Submission::SUBMISSION_STATE_DRAFT, $submission->getSubmissionState());

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getSubmissionState(), $submissionPersistence->getSubmissionState());
    }

    public function testAddSubmissionWithOneFileAttribute(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);

        $submission = $this->formalizeService->addSubmission($submission);
        $this->assertCount(1, $submission->getSubmittedFiles());
        /** @var SubmittedFile $submittedFile */
        $submittedFile = $submission->getSubmittedFiles()[0];
        $this->assertNotNull($submittedFile->getIdentifier());
        $this->assertNotNull($submittedFile->getFileDataIdentifier());
        $this->assertEquals('testFile', $submittedFile->getFileAttributeName());
        $this->assertEquals($uploadedFile->getClientOriginalName(), $submittedFile->getFilename());
        $this->assertEquals($uploadedFile->getSize(), $submittedFile->getFileSize());
        $this->assertEquals($uploadedFile->getMimeType(), $submittedFile->getMimeType());

        $this->checkSubmittedFilePersistence($submission);
    }

    public function testAddSubmissionWithTwoFileAttributes(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);
        $uploadedPdf = new UploadedFile(__DIR__.'/../Data/test.pdf', 'test.pdf', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);
        $this->submittedFileService->addSubmittedFilesToSubmission('optionalFiles',
            [$uploadedPdf, $uploadedFile], $submission);

        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertCount(3, $submission->getSubmittedFiles());
        $this->checkSubmittedFilePersistence($submission);

        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFile): bool {
                return $submittedFile->getFileAttributeName() === 'testFile'
                    && $submittedFile->getFilename() === $uploadedFile->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFile->getSize()
                    && $submittedFile->getMimeType() === $uploadedFile->getMimeType();
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFile): bool {
                return $submittedFile->getFileAttributeName() === 'optionalFiles'
                    && $submittedFile->getFilename() === $uploadedFile->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFile->getSize()
                    && $submittedFile->getMimeType() === $uploadedFile->getMimeType();
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedPdf): bool {
                return $submittedFile->getFileAttributeName() === 'optionalFiles'
                    && $submittedFile->getFilename() === $uploadedPdf->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedPdf->getSize()
                    && $submittedFile->getMimeType() === $uploadedPdf->getMimeType();
            }));
    }

    public function testAddSubmissionWithFilesSchemaViolationNoFiles(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA); // schema without files

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('Expected an ApiError for invalid schema, but none was thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(FormalizeService::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddSubmissionWithOneFileAttributeSchemaAutogeneration(): void
    {
        $form = $this->testEntityManager->addForm();
        $this->assertNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);

        $submission = $this->formalizeService->addSubmission($submission);
        $this->assertCount(1, $submission->getSubmittedFiles());

        $this->assertNotNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('foo',
            [$uploadedFile], $submission);

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('Expected an ApiError for invalid schema, but none was thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(FormalizeService::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddSubmissionWithFilesSchemaViolationToManyFiles(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile, $uploadedFile], $submission); // two files, where only one is allowed

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('Expected an ApiError for invalid schema, but none was thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(FormalizeService::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddSubmissionWithFilesSchemaViolationMimeType(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        // disallowed mime-type
        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.pdf', 'test.pdf', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('Expected an ApiError for invalid schema, but none was thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(FormalizeService::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID, $apiError->getErrorId());
        }
    }

    public function testAddSubmissionFormMissingError()
    {
        try {
            $submission = new Submission();
            $submission->setDataFeedElement('{}');
            $this->formalizeService->addSubmission($submission);
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('field \'form\' is required', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-field-missing', $apiError->getErrorId());
            $this->assertEquals('form', $apiError->getErrorDetails()[0]);
        }
    }

    public function testAddSubmissionDataFeedElementMissingError()
    {
        try {
            $submission = new Submission();
            $submission->setForm($this->testEntityManager->addForm());
            $this->formalizeService->addSubmission($submission);
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('field \'dataFeedElement\' is required', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-field-missing', $apiError->getErrorId());
            $this->assertEquals('dataFeedElement', $apiError->getErrorDetails()[0]);
        }
    }

    public function testAddSubmissionToFormWithDataFeedSchema()
    {
        $testDataFeedSchema = '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["givenName", "familyName"]
        }';

        $form = $this->testEntityManager->addForm('people', $testDataFeedSchema);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());

        $formPersistence = $submissionPersistence->getForm();
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());
    }

    /**
     * @throws \Exception
     */
    public function testAddSubmissionFormAvailable()
    {
        $testName = self::TEST_FORM_NAME;

        $form = new Form();
        $form->setName($testName);
        $utcTimezone = new \DateTimeZone('UTC');
        $start = (new \DateTime('now', $utcTimezone))->sub(\DateInterval::createFromDateString('1 minutes'));
        $end = (new \DateTime('now', $utcTimezone))->add(\DateInterval::createFromDateString('1 minutes'));
        $form->setAvailabilityStarts($start);
        $form->setAvailabilityEnds($end);
        $this->formalizeService->addForm($form);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        $submission = $this->formalizeService->addSubmission($submission);

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
    }

    /**
     * @throws \Exception
     */
    public function testAddSubmissionFormCurrentlyNotAvailable()
    {
        // test availability has already ended
        $form = new Form();
        $form->setName(self::TEST_FORM_NAME);
        $utcTimezone = new \DateTimeZone('UTC');

        // test availability has ended
        $start = (new \DateTime('now', $utcTimezone))->sub(\DateInterval::createFromDateString('2 minutes'));
        $end = (new \DateTime('now', $utcTimezone))->sub(\DateInterval::createFromDateString('1 minutes'));
        $form->setAvailabilityStarts($start);
        $form->setAvailabilityEnds($end);
        $this->formalizeService->addForm($form);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-form-currently-not-available', $apiError->getErrorId());
        }

        // test availability has not yet started
        $start = (new \DateTime('now', $utcTimezone))->add(\DateInterval::createFromDateString('1 minutes'));
        $end = (new \DateTime('now', $utcTimezone))->add(\DateInterval::createFromDateString('2 minutes'));
        $form->setAvailabilityStarts($start);
        $form->setAvailabilityEnds($end);
        $this->formalizeService->updateForm($form);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-form-currently-not-available', $apiError->getErrorId());
        }
    }

    public function testAddSubmissionMaxNumSubmissionsPerCreatorReached()
    {
        $form = $this->testEntityManager->addForm(maxNumSubmissionsPerCreator: 1);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        $this->login(self::ANOTHER_USER_IDENTIFIER);
        $this->formalizeService->addSubmission($submission); // first of another user -> ok

        $this->login(self::CURRENT_USER_IDENTIFIER);
        $this->formalizeService->addSubmission($submission); // first of current user -> ok
        try {
            $this->formalizeService->addSubmission($submission); // second of current user -> error
            $this->fail('ApiError not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
            $this->assertEquals(FormalizeService::MAX_NUM_FORM_SUBMISSIONS_PER_CREATOR_REACHED_ERROR_ID,
                $apiError->getErrorId());
        }
    }

    public function testGetSubmission()
    {
        $submission = $this->testEntityManager->addSubmission(null, '{"foo": "bar"}');

        $submissionPersistence = $this->formalizeService->getSubmissionByIdentifier($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());
    }

    public function testGetSubmissionNotFound()
    {
        try {
            $this->formalizeService->getSubmissionByIdentifier('404');
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
        }
    }

    public function testGetSubmittedFormSubmissions()
    {
        $form1 = $this->testEntityManager->addForm();
        $submission1 = $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');

        $form2 = $this->testEntityManager->addForm(allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED);
        $submission2_1 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_2 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_3 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $this->testEntityManager->addSubmission($form2, submissionState: Submission::SUBMISSION_STATE_DRAFT);

        $form3 = $this->testEntityManager->addForm();

        $submissions = $this->formalizeService->getSubmittedFormSubmissions($form1->getIdentifier());
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        // NOTE: drafts must not be returned
        $submissions = $this->formalizeService->getSubmittedFormSubmissions($form2->getIdentifier());
        $this->assertCount(3, $submissions);
        $this->assertEquals($submission2_1->getIdentifier(), $submissions[0]->getIdentifier());
        $this->assertEquals($submission2_2->getIdentifier(), $submissions[1]->getIdentifier());
        $this->assertEquals($submission2_3->getIdentifier(), $submissions[2]->getIdentifier());

        // test pagination:
        $submissionPage1 = $this->formalizeService->getSubmittedFormSubmissions($form2->getIdentifier(), [], 0, 2);
        $this->assertCount(2, $submissionPage1);

        $submissionPage2 = $this->formalizeService->getSubmittedFormSubmissions($form2->getIdentifier(), [], 2, 2);
        $this->assertCount(1, $submissionPage2);

        $submissionPage3 = $this->formalizeService->getSubmittedFormSubmissions($form2->getIdentifier(), [], 4, 2);
        $this->assertCount(0, $submissionPage3);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertResourcesAreAPermutationOf($submissions, [$submission2_1, $submission2_2, $submission2_3]);

        $submissions = $this->formalizeService->getSubmittedFormSubmissions($form3->getIdentifier());
        $this->assertCount(0, $submissions);

        $submissions = $this->formalizeService->getSubmittedFormSubmissions('foo');
        $this->assertCount(0, $submissions);
    }

    //    /**
    //     * Test commented because sqlite in its current version doesn't support the JSON_KEYS function.
    //     */
    //    public function testGetSubmissionsByFormIdWithOutputValidationKeys()
    //    {
    //        $form = $this->testEntityManager->addForm();
    //
    //        $submission0 = new Submission();
    //        $submission0->setDataFeedElement('{"foo": "bar"}');
    //        $submission0->setForm($form);
    //        $this->formalizeService->addSubmission($submission0);
    //
    //        $this->testEntityManager->addSubmission($form, '{"bar": "baz"}');
    //        $submission2 = $this->testEntityManager->addSubmission($form, '{"foo": "baz"}');
    //        $this->testEntityManager->addSubmission($form, '{"foo": "baz", "bar": 2}');
    //
    //        $submissions = $this->formalizeService->getSubmissionsByForm($form->getIdentifier());
    //        $this->assertCount(4, $submissions);
    //
    //        $submissions = $this->formalizeService->getSubmissionsByForm($form->getIdentifier(),
    //            [FormalizeService::OUTPUT_VALIDATION_FILTER => FormalizeService::OUTPUT_VALIDATION_KEYS]);
    //        $this->assertCount(2, $submissions);
    //        $this->assertResourcesAreAPermutationOf($submissions, [$submission0, $submission2]);
    //    }

    public function testUpdateSubmission(): void
    {
        $form = $this->testEntityManager->addForm(
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED | Submission::SUBMISSION_STATE_DRAFT);

        $submission = $this->testEntityManager->addSubmission($form,
            dataFeedElement: '{"foo": "bar"}',
            submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $previousSubmission = clone $submission;

        $this->assertEquals($submission->getDateCreated(), $submission->getDateLastModified());
        $creationDate = $submission->getDateCreated();

        $submission->setDataFeedElement('{"foo": "baz"}');
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_SUBMITTED);
        $submission = $this->formalizeService->updateSubmission($submission, $previousSubmission);
        $this->assertEquals('{"foo": "baz"}', $submission->getDataFeedElement());
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submission->getSubmissionState());
        $this->assertLessThan($submission->getDateLastModified(), $creationDate);

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertEquals($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertEquals($submission->getSubmissionState(), $submissionPersistence->getSubmissionState());

        $this->assertFalse($this->testSubmissionEventSubscriber->wasUpdateSubmissionPostEventCalled());

        $previousSubmission = clone $submission;
        $submission->setSubmissionState(Submission::SUBMISSION_STATE_SUBMITTED);
        $this->formalizeService->updateSubmission($submission, $previousSubmission);

        $this->assertTrue($this->testSubmissionEventSubscriber->wasUpdateSubmissionPostEventCalled());
    }

    public function testUpdateSubmissionWithOneFileAttribute(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);

        $submission = $this->formalizeService->addSubmission($submission);
        $previousSubmission = clone $submission;

        $this->assertCount(1, $submission->getSubmittedFiles());
        /** @var SubmittedFile $previouslySubmittedFile */
        $previouslySubmittedFile = $submission->getSubmittedFiles()[0];

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test-updated.txt', 'test-updated.txt', test: true);
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile', [$uploadedFile], $submission);
        $this->submittedFileService->removeSubmittedFilesFromSubmission([$submission->getSubmittedFiles()[0]->getIdentifier()], $submission);

        $submission = $this->formalizeService->updateSubmission($submission, $previousSubmission);
        $this->assertCount(1, $submission->getSubmittedFiles());
        /** @var SubmittedFile $submittedFile */
        $submittedFile = $submission->getSubmittedFiles()[0];
        $this->assertNotNull($submittedFile->getIdentifier());
        $this->assertNotNull($submittedFile->getFileDataIdentifier());
        $this->assertEquals('testFile', $submittedFile->getFileAttributeName());
        $this->assertEquals($uploadedFile->getClientOriginalName(), $submittedFile->getFilename());
        $this->assertEquals($uploadedFile->getSize(), $submittedFile->getFileSize());
        $this->assertEquals($uploadedFile->getMimeType(), $submittedFile->getMimeType());

        $submittedFilePersistence = $this->testEntityManager->getSubmittedFile($submittedFile->getIdentifier());
        $this->assertEquals($submittedFile->getIdentifier(), $submittedFilePersistence->getIdentifier());
        $this->assertEquals($submittedFile->getFileDataIdentifier(), $submittedFilePersistence->getFileDataIdentifier());
        $this->assertEquals($submittedFile->getFileAttributeName(), $submittedFilePersistence->getFileAttributeName());
        $this->assertEquals($submittedFile->getFileName(), $submittedFilePersistence->getFilename());
        $this->assertEquals($submittedFile->getFileSize(), $submittedFilePersistence->getFileSize());
        $this->assertEquals($submittedFile->getMimeType(), $submittedFilePersistence->getMimeType());

        $blobFile = $this->fileApi->getFile($submittedFile->getFileDataIdentifier());
        $this->assertEquals($submittedFile->getFileName(), $blobFile->getFileName());
        $this->assertEquals($submittedFile->getFileSize(), $blobFile->getFileSize());
        $this->assertEquals($submittedFile->getMimeType(), $blobFile->getMimeType());
        $this->assertEquals($submission->getIdentifier(), $blobFile->getPrefix());

        $this->assertNull($this->testEntityManager->getSubmittedFile($previouslySubmittedFile->getIdentifier()));
        try {
            $this->fileApi->getFile($previouslySubmittedFile->getFileDataIdentifier());
            $this->fail('Expected exception for non-existent file');
        } catch (FileApiException $fileApiException) {
            $this->assertEquals(FileApiException::FILE_NOT_FOUND, $fileApiException->getCode());
        }
    }

    public function testUpdateSubmissionWithTwoFileAttributesJustAdd(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);
        $uploadedPdf = new UploadedFile(__DIR__.'/../Data/test.pdf', 'test.pdf', test: true);
        $uploadedFileUpdated = new UploadedFile(__DIR__.'/../Data/test-updated.txt', 'test-updated.txt', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);
        $this->submittedFileService->addSubmittedFilesToSubmission('optionalFiles',
            [$uploadedPdf], $submission);

        $submission = $this->formalizeService->addSubmission($submission);
        $previousSubmission = clone $submission;

        $this->submittedFileService->addSubmittedFilesToSubmission('optionalFiles',
            [$uploadedFileUpdated], $submission);
        $submission = $this->formalizeService->updateSubmission($submission, $previousSubmission);

        $this->assertCount(3, $submission->getSubmittedFiles());
        $this->checkSubmittedFilePersistence($submission);

        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFile): bool {
                return $submittedFile->getFileAttributeName() === 'testFile'
                    && $submittedFile->getFilename() === $uploadedFile->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFile->getSize()
                    && $submittedFile->getMimeType() === $uploadedFile->getMimeType();
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedPdf): bool {
                return $submittedFile->getFileAttributeName() === 'optionalFiles'
                    && $submittedFile->getFilename() === $uploadedPdf->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedPdf->getSize()
                    && $submittedFile->getMimeType() === $uploadedPdf->getMimeType();
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFileUpdated): bool {
                return $submittedFile->getFileAttributeName() === 'optionalFiles'
                    && $submittedFile->getFilename() === $uploadedFileUpdated->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFileUpdated->getSize()
                    && $submittedFile->getMimeType() === $uploadedFileUpdated->getMimeType();
            }));
    }

    public function testUpdateSubmissionWithTwoFileAttributesJustDelete(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);
        $uploadedPdf = new UploadedFile(__DIR__.'/../Data/test.pdf', 'test.pdf', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);
        $this->submittedFileService->addSubmittedFilesToSubmission('optionalFiles',
            [$uploadedPdf], $submission);

        $submission = $this->formalizeService->addSubmission($submission);
        $this->assertCount(2, $submission->getSubmittedFiles());
        $previousSubmission = clone $submission;

        // get a fresh UnitOfWork
        $submission = $this->testEntityManager->getSubmission($submission->getIdentifier());

        $submittedFileToDelete = $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile): bool {
                return $submittedFile->getFileAttributeName() === 'optionalFiles';
            });
        $this->assertCount(1, $submittedFileToDelete);

        $this->submittedFileService->removeSubmittedFilesFromSubmission([$submittedFileToDelete[0]->getIdentifier()], $submission);
        $submission = $this->formalizeService->updateSubmission($submission, $previousSubmission);

        $this->assertCount(1, $submission->getSubmittedFiles());
        $this->checkSubmittedFilePersistence($submission);

        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFile): bool {
                return $submittedFile->getFileAttributeName() === 'testFile'
                    && $submittedFile->getFilename() === $uploadedFile->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFile->getSize()
                    && $submittedFile->getMimeType() === $uploadedFile->getMimeType();
            }));
    }

    public function testUpdateSubmissionWithTwoFileAttributesAddAndDelete(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: self::TEST_FORM_SCHEMA_WITH_TEST_FILE);

        $uploadedFile = new UploadedFile(__DIR__.'/../Data/test.txt', 'test.txt', test: true);
        $uploadedPdf = new UploadedFile(__DIR__.'/../Data/test.pdf', 'test.pdf', test: true);
        $uploadedFileUpdated = new UploadedFile(__DIR__.'/../Data/test-updated.txt', 'test-updated.txt', test: true);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName":"Jane","familyName":"Doe"}');
        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFile], $submission);
        $this->submittedFileService->addSubmittedFilesToSubmission('optionalFiles',
            [$uploadedPdf, $uploadedFile], $submission);

        $submission = $this->formalizeService->addSubmission($submission);
        $previousSubmission = clone $submission;

        // get a fresh UnitOfWork
        $submission = $this->testEntityManager->getSubmission($submission->getIdentifier());

        $submittedFileToDelete1 = $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile): bool {
                return $submittedFile->getFileAttributeName() === 'testFile';
            });
        $this->assertCount(1, $submittedFileToDelete1);
        $submittedFileToDelete2 = $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile): bool {
                return $submittedFile->getFileName() === 'test.pdf';
            });
        $this->assertCount(1, $submittedFileToDelete2);

        $this->submittedFileService->addSubmittedFilesToSubmission('testFile',
            [$uploadedFileUpdated], $submission);
        $this->submittedFileService->addSubmittedFilesToSubmission('optionalFiles',
            [$uploadedFileUpdated], $submission);

        $this->submittedFileService->removeSubmittedFilesFromSubmission(
            [$submittedFileToDelete1[0]->getIdentifier(), $submittedFileToDelete2[0]->getIdentifier()], $submission);

        $submission = $this->formalizeService->updateSubmission($submission, $previousSubmission);

        $this->assertCount(3, $submission->getSubmittedFiles());
        $this->checkSubmittedFilePersistence($submission);

        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFileUpdated): bool {
                return $submittedFile->getFileAttributeName() === 'testFile'
                    && $submittedFile->getFilename() === $uploadedFileUpdated->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFileUpdated->getSize()
                    && $submittedFile->getMimeType() === $uploadedFileUpdated->getMimeType();
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFile): bool {
                return $submittedFile->getFileAttributeName() === 'optionalFiles'
                    && $submittedFile->getFilename() === $uploadedFile->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFile->getSize()
                    && $submittedFile->getMimeType() === $uploadedFile->getMimeType();
            }));
        $this->assertCount(1, $this->selectWhere($submission->getSubmittedFiles()->getValues(),
            function (SubmittedFile $submittedFile) use ($uploadedFileUpdated): bool {
                return $submittedFile->getFileAttributeName() === 'optionalFiles'
                    && $submittedFile->getFilename() === $uploadedFileUpdated->getClientOriginalName()
                    && $submittedFile->getFileSize() === $uploadedFileUpdated->getSize()
                    && $submittedFile->getMimeType() === $uploadedFileUpdated->getMimeType();
            }));
    }

    public function testUpdateSubmissionSubmitDraftSchemaViolation(): void
    {
        $form = $this->testEntityManager->addForm(
            dataFeedSchema: '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["givenName", "familyName"]
        }',
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED | Submission::SUBMISSION_STATE_DRAFT);

        // non-compliant data for a draft is ok
        $submission = $this->testEntityManager->addSubmission($form,
            dataFeedElement: '{"givenName": "Jane"}', submissionState: Submission::SUBMISSION_STATE_DRAFT);
        $previousSubmission = clone $submission;

        $submission->setSubmissionState(Submission::SUBMISSION_STATE_SUBMITTED);
        try {
            // on submission the schema validation is expected to complain
            $this->formalizeService->updateSubmission($submission, $previousSubmission);
            $this->fail('ApiError not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals(FormalizeService::SUBMISSION_DATA_FEED_ELEMENT_INVALID_SCHEMA_ERROR_ID, $apiError->getErrorId());
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testRemoveSubmission()
    {
        $form = $this->testEntityManager->addForm();
        $submission = $this->testEntityManager->addSubmission($form);

        $this->assertNotNull($this->testEntityManager->getSubmission($submission->getIdentifier()));

        $this->formalizeService->removeSubmission($submission);

        $this->assertNull($this->testEntityManager->getSubmission($submission->getIdentifier()));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testRemoveAllFormSubmissions()
    {
        $form = $this->testEntityManager->addForm();
        $this->testEntityManager->addSubmission($form);
        $this->testEntityManager->addSubmission($form);

        $this->assertCount(2, $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));

        $this->formalizeService->removeAllSubmittedFormSubmissions($form->getIdentifier());

        $this->assertCount(0, $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionInvalidJsonError()
    {
        $form = $this->testEntityManager->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('foo');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub, clone $sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }
    }

    public function testAddSubmissionFormSchemaGeneration(): void
    {
        $form = $this->testEntityManager->addForm();

        $this->assertNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar", "fooNum": 1, "fooFloat": 4.2, "fooBool": false, "fooNull": null}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertNotNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "baz", "fooNum": 2, "fooFloat": 0.0, "fooBool": true, "fooNull": null}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionFormSchemaGenerationWithArrayProperty()
    {
        $form = $this->testEntityManager->addForm();

        // for empty arrays, the items type is guessed to be string
        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": [], "bar": [1, 2]}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertNotNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": ["baz"], "bar": [3]}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
    }

    public function testAddSubmissionFormSchemaGenerationSchemaViolationWrongKey()
    {
        $form = $this->testEntityManager->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm($form);
        $this->formalizeService->addSubmission($sub);

        $sub = new Submission();
        $sub->setDataFeedElement('{"fooz": "bar"}');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub, clone $sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }
    }

    public function testAddSubmissionFormSchemaGenerationSchemaViolationWrongType()
    {
        $form = $this->testEntityManager->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm($form);
        $this->formalizeService->addSubmission($sub);

        $sub->setDataFeedElement('{"foo": 41}');

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub, clone $sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws NotSupported
     * @throws \JsonException
     */
    public function testAddSubmissionToFormWithDataFeedSchemaSchemaViolationError()
    {
        $testDataFeedSchema = '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["familyName"],
            "additionalProperties": false
        }';

        $form = $this->testEntityManager->addForm('people', $testDataFeedSchema);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName": "John"}'); // required property 'familyName' missing

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        $submission->setDataFeedElement('{"familyName": "Doe", "email": "john@doe.com"}'); // undefined property 'email'

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }
    }

    public function testFormAuthorization()
    {
        $form = new Form();
        $form->setName('Test Form');
        $form = $this->formalizeService->addForm($form);

        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $this->authorizationService->getGrantedFormItemActions($form));

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToDeleteForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form));

        $this->formalizeService->removeForm($form);

        $this->assertEquals([], $this->authorizationService->getGrantedFormItemActions($form));

        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToDeleteForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form));
    }

    private function checkSubmittedFilePersistence(Submission $submission): void
    {
        /** @var SubmittedFile $submittedFile */
        foreach ($submission->getSubmittedFiles() as $submittedFile) {
            $this->assertNotNull($submittedFile->getIdentifier());
            $this->assertNotNull($submittedFile->getFileDataIdentifier());

            $submittedFilePersistence = $this->testEntityManager->getSubmittedFile($submittedFile->getIdentifier());
            $this->assertEquals($submittedFile->getIdentifier(), $submittedFilePersistence->getIdentifier());
            $this->assertEquals($submittedFile->getFileDataIdentifier(), $submittedFilePersistence->getFileDataIdentifier());
            $this->assertEquals($submittedFile->getFileAttributeName(), $submittedFilePersistence->getFileAttributeName());
            $this->assertEquals($submittedFile->getFileName(), $submittedFilePersistence->getFilename());
            $this->assertEquals($submittedFile->getFileSize(), $submittedFilePersistence->getFileSize());
            $this->assertEquals($submittedFile->getMimeType(), $submittedFilePersistence->getMimeType());

            $blobFile = $this->fileApi->getFile($submittedFile->getFileDataIdentifier());
            $this->assertEquals($submittedFile->getFileName(), $blobFile->getFileName());
            $this->assertEquals($submittedFile->getFileSize(), $blobFile->getFileSize());
            $this->assertEquals($submittedFile->getMimeType(), $blobFile->getMimeType());
            $this->assertEquals($submission->getIdentifier(), $blobFile->getPrefix());
        }
    }
}
