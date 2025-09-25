<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\BlobBundle\TestUtils\TestEntityManager as BlobTestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\AbstractApiTest;
use Dbp\Relay\CoreBundle\TestUtils\TestClient;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class ApiTest extends AbstractApiTest
{
    private const TEST_FORM_NAME = 'Test Form';
    private const TEST_DATA = [
        'givenName' => 'Joni',
        'familyName' => 'Doe',
    ];

    private const ANOTHER_TEST_USER_IDENTIFIER = TestClient::TEST_USER_IDENTIFIER.'_2';
    private ?TestEntityManager $testEntityManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login();
        AuthorizationTest::setUp($this->testClient->getContainer());
        $this->testEntityManager = new TestEntityManager($this->testClient->getContainer());
        BlobTestEntityManager::setUpBlobEntityManager($this->testClient->getContainer());
    }

    protected function getUserAttributeDefaultValues(): array
    {
        return ['MAY_CREATE_FORMS' => true];
    }

    protected function tearDown(): void
    {
        AuthorizationTest::tearDown($this->testClient->getContainer());
    }

    public function testUnauthorized()
    {
        $endpoints = [
            ['GET', '/formalize/submissions', 'application/ld+json'],
            ['POST', '/formalize/submissions', 'multipart/form-data'],
            ['DELETE', '/formalize/submissions', 'application/ld+json'],
            ['GET', '/formalize/submissions/123', 'application/ld+json'],
            ['PATCH', '/formalize/submissions/123', 'multipart/form-data'],
            ['DELETE', '/formalize/submissions/123', 'application/ld+json'],
            ['GET', '/formalize/forms', 'application/ld+json'],
            ['POST', '/formalize/forms', 'application/ld+json'],
            ['GET', '/formalize/forms/123', 'application/ld+json'],
            ['PATCH', '/formalize/forms/123', 'application/merge-patch+json'],
            ['DELETE', '/formalize/forms/123', 'application/ld+json'],
        ];

        foreach ($endpoints as [$method, $path, $contentType]) {
            $options = [
                'headers' => [
                    'Content-Type' => $contentType,
                ]];
            $response = $this->testClient->request($method, $path, $options, null);
            $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(),
                'Expected '.Response::HTTP_UNAUTHORIZED.', got '.$response->getStatusCode().' for '.$method.' '.$path);
        }
    }

    public function testCreateForm(): void
    {
        $formData = $this->createTestForm();

        $this->assertNotNull($formData['identifier']);
        $this->assertEquals(self::TEST_FORM_NAME, $formData['name']);
        $this->assertEquals(AbstractTestCase::TEST_FORM_SCHEMA, $formData['dataFeedSchema']);
        $this->assertNotEmpty($formData['dateCreated']);
        $this->assertNull($formData['availabilityStarts']);
        $this->assertNull($formData['availabilityEnds']);
        $this->assertEquals(4, $formData['allowedSubmissionStates']);
        $this->assertEquals([], $formData['allowedActionsWhenSubmitted']);
        $this->assertEquals(10, $formData['maxNumSubmissionsPerCreator']);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $formData['grantedActions']);
        $this->assertEquals(0, $formData['numSubmissionsByCurrentUser']);
    }

    // fails on dev for unknown reason
    public function testCreateFormForbidden(): void
    {
        $this->login(userAttributes: ['MAY_CREATE_FORMS' => false]);
        $data = [
            'name' => self::TEST_FORM_NAME,
        ];

        $response = $this->testClient->postJson('/formalize/forms', $data);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetForm(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $response = $this->testClient->get('/formalize/forms/'.$formIdentifier);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $formData = json_decode($response->getContent(false), true);
        $this->assertEquals($formIdentifier, $formData['identifier']);
        $this->assertEquals(self::TEST_FORM_NAME, $formData['name']);
        $this->assertEquals(AbstractTestCase::TEST_FORM_SCHEMA, $formData['dataFeedSchema']);
        $this->assertNotEmpty($formData['dateCreated']);
        $this->assertNull($formData['availabilityStarts']);
        $this->assertNull($formData['availabilityEnds']);
        $this->assertEquals(4, $formData['allowedSubmissionStates']);
        $this->assertEquals([], $formData['allowedActionsWhenSubmitted']);
        $this->assertEquals(10, $formData['maxNumSubmissionsPerCreator']);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $formData['grantedActions']);
        $this->assertEquals(0, $formData['numSubmissionsByCurrentUser']);
    }

    public function testGetFormForbidden(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        // log in user other than the creator of the form
        $this->login(self::ANOTHER_TEST_USER_IDENTIFIER);

        $response = $this->testClient->get('/formalize/forms/'.$formIdentifier);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testGetForms(): void
    {
        $form1 = $this->createTestForm();
        $form2 = $this->createTestForm();

        $response = $this->testClient->get('/formalize/forms');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $formDataCollection = json_decode($response->getContent(false), true)['hydra:member'] ?? [];
        $this->assertCount(2, $formDataCollection);

        foreach ($formDataCollection as $formData) {
            $this->assertContains($formData['identifier'], [$form1['identifier'], $form2['identifier']]);
            $this->assertEquals(self::TEST_FORM_NAME, $formData['name']);
            $this->assertEquals(AbstractTestCase::TEST_FORM_SCHEMA, $formData['dataFeedSchema']);
            $this->assertNotEmpty($formData['dateCreated']);
            $this->assertNull($formData['availabilityStarts']);
            $this->assertNull($formData['availabilityEnds']);
            $this->assertEquals(4, $formData['allowedSubmissionStates']);
            $this->assertEquals([], $formData['allowedActionsWhenSubmitted']);
            $this->assertEquals(10, $formData['maxNumSubmissionsPerCreator']);
            $this->assertEquals([AuthorizationService::MANAGE_ACTION], $formData['grantedActions']);
            $this->assertArrayNotHasKey('numSubmissionsByCurrentUser', $formData); // only available for item operations
        }
    }

    public function testPatchForm(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $updatedFormName = 'Updated '.self::TEST_FORM_NAME;
        $newData = [
            'name' => $updatedFormName,
        ];

        $response = $this->testClient->patchJson('/formalize/forms/'.$formIdentifier, $newData);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $updatedFormData = json_decode($response->getContent(false), true);
        $this->assertEquals($formIdentifier, $updatedFormData['identifier']);
        $this->assertEquals($updatedFormName, $updatedFormData['name']);
        $this->assertEquals(AbstractTestCase::TEST_FORM_SCHEMA, $updatedFormData['dataFeedSchema']);
        $this->assertNotEmpty($updatedFormData['dateCreated']);
        $this->assertNull($updatedFormData['availabilityStarts']);
        $this->assertNull($updatedFormData['availabilityEnds']);
        $this->assertEquals(4, $updatedFormData['allowedSubmissionStates']);
        $this->assertEquals([], $updatedFormData['allowedActionsWhenSubmitted']);
        $this->assertEquals(10, $updatedFormData['maxNumSubmissionsPerCreator']);
        $this->assertEquals([AuthorizationService::MANAGE_ACTION], $updatedFormData['grantedActions']);
        $this->assertEquals(0, $updatedFormData['numSubmissionsByCurrentUser']);
    }

    public function testPatchFormBackwardCompatibility(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $updatedDataSchema = json_decode(AbstractTestCase::TEST_FORM_SCHEMA, true);
        $updatedDataSchema['properties'] = [
            'birthday' => [
                'type' => 'string',
            ],
        ];

        $updatedDataSchemaJson = json_encode($updatedDataSchema);
        $newData = [
            'dataFeedSchema' => $updatedDataSchemaJson,
        ];

        $response = $this->testClient->patchJson('/formalize/forms/'.$formIdentifier, $newData);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $updatedFormData = json_decode($response->getContent(false), true);
        $this->assertEquals($formIdentifier, $updatedFormData['identifier']);
        $this->assertEquals(self::TEST_FORM_NAME, $updatedFormData['name']);
        $this->assertEquals($updatedDataSchemaJson, $updatedFormData['dataFeedSchema']);
    }

    public function testPatchFormForbidden(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $newData = [
            'name' => 'Updated '.self::TEST_FORM_NAME,
        ];

        // log in user other than the creator of the form
        $this->login(self::ANOTHER_TEST_USER_IDENTIFIER);

        $response = $this->testClient->patchJson('/formalize/forms/'.$formIdentifier, $newData);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testDeleteForm(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $response = $this->testClient->get('/formalize/forms/'.$formIdentifier);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $response = $this->testClient->delete('/formalize/forms/'.$formIdentifier);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $response = $this->testClient->get('/formalize/forms/'.$formIdentifier);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteFormForbidden(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        // log in user other than the creator of the form
        $this->login(self::ANOTHER_TEST_USER_IDENTIFIER);

        $response = $this->testClient->delete('/formalize/forms/'.$formIdentifier);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testCreateSubmissionWithDeprecatedFormatJsonLd(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = $this->postSubmissionWithDeprecateFormatJsonLd($formIdentifier);
        $this->assertNotNull($submissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA, flags: JSON_THROW_ON_ERROR), $submissionData['dataFeedElement']);
    }

    public function testCreateSubmission(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = $this->postSubmission($formIdentifier);
        $this->assertTrue(Uuid::isValid($submissionData['identifier']));
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA), $submissionData['dataFeedElement']);
        $this->assertEquals([], $submissionData['tags']);
    }

    public function testCreateSubmissionToFormWithoutAvailableTags(): void
    {
        $form = $this->createTestForm(availableTags: null);
        $formIdentifier = $form['identifier'];

        $submissionData = $this->postSubmission($formIdentifier);
        $this->assertNotNull($submissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA), $submissionData['dataFeedElement']);
        $this->assertEquals([], $submissionData['tags']);
    }

    public function testCreateSubmissionWithTags(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $tags = [AbstractTestCase::TEST_AVAILABLE_TAGS[0], AbstractTestCase::TEST_AVAILABLE_TAGS[2]];
        $submissionData = $this->postSubmission($formIdentifier, tags: $tags);
        $this->assertNotNull($submissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA), $submissionData['dataFeedElement']);
        $this->assertEquals($tags, $submissionData['tags']);
    }

    public function testCreateSubmissionWithFiles(): void
    {
        $form = $this->createTestForm(dataFeedSchema: AbstractTestCase::TEST_FORM_SCHEMA_WITH_TEST_FILE);
        $formIdentifier = $form['identifier'];

        $uploadedTextFile = new UploadedFile(AbstractTestCase::TEXT_FILE_PATH, AbstractTestCase::TEXT_FILE_NAME);
        $uploadedPdfFile = new UploadedFile(AbstractTestCase::PDF_FILE_PATH, AbstractTestCase::PDF_FILE_PATH);
        $files = [
            'testFile' => $uploadedTextFile,
            'optionalFiles' => [$uploadedPdfFile],
        ];

        $submissionData = $this->postSubmission($formIdentifier, files: $files);
        $this->assertTrue(Uuid::isValid($submissionData['identifier']));
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA, flags: JSON_THROW_ON_ERROR), $submissionData['dataFeedElement']);
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submissionData['submissionState']);
        $this->assertArrayHasKey('submittedFiles', $submissionData);
        $submittedFiles = $submissionData['submittedFiles'];
        $this->assertCount(2, $submittedFiles);

        $testFiles = TestUtils::selectWhere($submittedFiles, function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'testFile';
        });
        $this->assertCount(1, $testFiles);
        $submittedTextFile = $testFiles[0];
        $this->assertTrue(Uuid::isValid($submittedTextFile['identifier']));
        $this->assertEquals('testFile', $submittedTextFile['fileAttributeName']);
        $this->assertEquals(AbstractTestCase::TEXT_FILE_NAME, $submittedTextFile['fileName']);
        $this->assertEquals($uploadedTextFile->getSize(), $submittedTextFile['fileSize']);
        $this->assertEquals('text/plain', $submittedTextFile['mimeType']);

        $optionalFiles = TestUtils::selectWhere($submittedFiles, function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'optionalFiles';
        });
        $this->assertCount(1, $optionalFiles);
        $submittedPdfFile = $optionalFiles[0];
        $this->assertTrue(Uuid::isValid($submittedPdfFile['identifier']));
        $this->assertEquals('optionalFiles', $submittedPdfFile['fileAttributeName']);
        $this->assertEquals(AbstractTestCase::PDF_FILE_NAME, $submittedPdfFile['fileName']);
        $this->assertEquals($uploadedPdfFile->getSize(), $submittedPdfFile['fileSize']);
        $this->assertEquals('application/pdf', $submittedPdfFile['mimeType']);
    }

    public function testCreateSubmissionWithFilesFileSchemaViolation(): void
    {
        $form = $this->createTestForm(dataFeedSchema: AbstractTestCase::TEST_FORM_SCHEMA_WITH_TEST_FILE);
        $formIdentifier = $form['identifier'];

        $uploadedPdfFile = new UploadedFile(AbstractTestCase::PDF_FILE_PATH, AbstractTestCase::PDF_FILE_PATH);
        $files = [
            'optionalFiles' => [$uploadedPdfFile],
        ];

        $errorData = $this->postSubmission($formIdentifier, files: $files, expectedStatusCode: Response::HTTP_BAD_REQUEST);
        $this->assertEquals('formalize:submission-submitted-files-invalid-schema', $errorData['relay:errorId']);
    }

    // fails on dev for unknown reason
    public function testCreateSubmissionForbidden(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        // log in user other than the creator of the form
        $this->login(self::ANOTHER_TEST_USER_IDENTIFIER);

        $this->postSubmission($formIdentifier, expectedStatusCode: Response::HTTP_FORBIDDEN);
    }

    public function testCreateEmptySubmission(): void
    {
        $form = $this->createTestForm(dataFeedSchema: null);
        $formIdentifier = $form['identifier'];

        $submissionData = $this->postSubmission($formIdentifier, []);
        $this->assertEquals('{}', $submissionData['dataFeedElement']);
    }

    public function testCreateSubmissionWithInvalidSchema(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $errorData = $this->postSubmission($formIdentifier, ['foo' => 'bar'], expectedStatusCode: Response::HTTP_BAD_REQUEST);
        $this->assertEquals('formalize:submission-data-feed-invalid-schema', $errorData['relay:errorId']);
    }

    public function testGetSubmission(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = $this->postSubmissionWithDeprecateFormatJsonLd($formIdentifier);
        $submissionIdentifier = $submissionData['identifier'];

        $response = $this->testClient->get('/formalize/submissions/'.$submissionIdentifier);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $submissionData = json_decode($response->getContent(false), true);
        $this->assertEquals($submissionIdentifier, $submissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA), $submissionData['dataFeedElement']);
    }

    public function testGetSubmissionForbidden(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = $this->postSubmissionWithDeprecateFormatJsonLd($formIdentifier);
        $submissionIdentifier = $submissionData['identifier'];

        // log in user other than the creator of the submission
        $this->login(self::ANOTHER_TEST_USER_IDENTIFIER);

        $response = $this->testClient->get('/formalize/submissions/'.$submissionIdentifier);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPatchSubmission(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = $this->postSubmission($formIdentifier);
        $submissionIdentifier = $submissionData['identifier'];

        $updatedData = [
            'givenName' => 'John',
            'familyName' => 'Smith',
        ];

        $updatedSubmissionData = $this->patchSubmission($submissionIdentifier, $updatedData);
        $this->assertEquals($submissionIdentifier, $updatedSubmissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $updatedSubmissionData['form']);
        $this->assertEquals(json_encode($updatedData), $updatedSubmissionData['dataFeedElement']);
    }

    public function testPatchSubmissionWithTags(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $tags = ['tag1', 'tag2'];
        $submissionData = $this->postSubmission($formIdentifier, tags: $tags);
        $submissionIdentifier = $submissionData['identifier'];

        $updatedData = [
            'givenName' => 'John',
            'familyName' => 'Smith',
        ];
        $tags = ['tag2', 'tag3'];

        $updatedSubmissionData = $this->patchSubmission($submissionIdentifier, $updatedData, tags: $tags);
        $this->assertEquals($submissionIdentifier, $updatedSubmissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $updatedSubmissionData['form']);
        $this->assertEquals(json_encode($updatedData), $updatedSubmissionData['dataFeedElement']);
        $this->assertEquals($tags, $updatedSubmissionData['tags']);
    }

    public function testPatchSubmissionWithFiles(): void
    {
        $form = $this->createTestForm(dataFeedSchema: AbstractTestCase::TEST_FORM_SCHEMA_WITH_TEST_FILE);
        $formIdentifier = $form['identifier'];

        $uploadedTextFile = new UploadedFile(AbstractTestCase::TEXT_FILE_PATH, AbstractTestCase::TEXT_FILE_NAME);
        $uploadedTextFile2 = new UploadedFile(AbstractTestCase::TEXT_FILE_2_PATH, AbstractTestCase::TEXT_FILE_2_NAME);
        $uploadedPdfFile = new UploadedFile(AbstractTestCase::PDF_FILE_PATH, AbstractTestCase::PDF_FILE_PATH);

        $files = [
            'testFile' => $uploadedTextFile,
            'optionalFiles' => [$uploadedPdfFile],
        ];
        $submissionData = $this->postSubmission($formIdentifier, files: $files);

        // we now replace 'testFile' and add another file to 'optionalFiles':
        $submissionIdentifier = $submissionData['identifier'];
        $testFiles = TestUtils::selectWhere($submissionData['submittedFiles'], function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'testFile';
        });
        $this->assertCount(1, $testFiles);

        $files = [
            'testFile' => [$uploadedTextFile2],
            'optionalFiles' => $uploadedTextFile2,
        ];
        $submittedFilesToDelete = [
            $testFiles[0]['identifier'],
        ];
        $submissionData = $this->patchSubmission($submissionIdentifier, files: $files, submittedFilesToDelete: $submittedFilesToDelete);
        $this->assertTrue(Uuid::isValid($submissionData['identifier']));
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA, flags: JSON_THROW_ON_ERROR), $submissionData['dataFeedElement']);
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submissionData['submissionState']);
        $this->assertArrayHasKey('submittedFiles', $submissionData);
        $submittedFiles = $submissionData['submittedFiles'];
        $this->assertCount(3, $submittedFiles);

        $testFiles = TestUtils::selectWhere($submittedFiles, function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'testFile';
        });
        $this->assertCount(1, $testFiles);
        $submittedTextFile = $testFiles[0];
        $this->assertTrue(Uuid::isValid($submittedTextFile['identifier']));
        $this->assertEquals('testFile', $submittedTextFile['fileAttributeName']);
        $this->assertEquals(AbstractTestCase::TEXT_FILE_2_NAME, $submittedTextFile['fileName']);
        $this->assertEquals($uploadedTextFile2->getSize(), $submittedTextFile['fileSize']);
        $this->assertEquals('text/plain', $submittedTextFile['mimeType']);

        $optionalPdfFiles = TestUtils::selectWhere($submittedFiles, function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'optionalFiles'
                && $submittedFileData['mimeType'] === 'application/pdf';
        });
        $this->assertCount(1, $optionalPdfFiles);
        $submittedPdfFile = $optionalPdfFiles[0];
        $this->assertTrue(Uuid::isValid($submittedPdfFile['identifier']));
        $this->assertEquals('optionalFiles', $submittedPdfFile['fileAttributeName']);
        $this->assertEquals(AbstractTestCase::PDF_FILE_NAME, $submittedPdfFile['fileName']);
        $this->assertEquals($uploadedPdfFile->getSize(), $submittedPdfFile['fileSize']);
        $this->assertEquals('application/pdf', $submittedPdfFile['mimeType']);

        $optionalTextFiles = TestUtils::selectWhere($submittedFiles, function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'optionalFiles'
                && $submittedFileData['mimeType'] === 'text/plain';
        });
        $this->assertCount(1, $optionalTextFiles);
        $submittedTextFile = $optionalTextFiles[0];
        $this->assertTrue(Uuid::isValid($submittedTextFile['identifier']));
        $this->assertEquals('optionalFiles', $submittedTextFile['fileAttributeName']);
        $this->assertEquals(AbstractTestCase::TEXT_FILE_2_NAME, $submittedTextFile['fileName']);
        $this->assertEquals($uploadedTextFile2->getSize(), $submittedTextFile['fileSize']);
        $this->assertEquals('text/plain', $submittedTextFile['mimeType']);
    }

    public function testPatchSubmissionWithFiles2(): void
    {
        $form = $this->createTestForm(dataFeedSchema: AbstractTestCase::TEST_FORM_SCHEMA_WITH_TEST_FILE);
        $formIdentifier = $form['identifier'];

        $uploadedTextFile = new UploadedFile(AbstractTestCase::TEXT_FILE_PATH, AbstractTestCase::TEXT_FILE_NAME);
        $uploadedTextFile2 = new UploadedFile(AbstractTestCase::TEXT_FILE_2_PATH, AbstractTestCase::TEXT_FILE_2_NAME);
        $uploadedPdfFile = new UploadedFile(AbstractTestCase::PDF_FILE_PATH, AbstractTestCase::PDF_FILE_PATH);

        $files = [
            'testFile' => $uploadedTextFile,
            'optionalFiles' => [$uploadedPdfFile, $uploadedTextFile2],
        ];
        $submissionData = $this->postSubmission($formIdentifier, files: $files);

        // we now replace 'testFile' and delete the pdf file from 'optionalFiles':
        $submissionIdentifier = $submissionData['identifier'];
        $testFiles = TestUtils::selectWhere($submissionData['submittedFiles'], function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'testFile';
        });
        $this->assertCount(1, $testFiles);
        $optionalPdfFiles = TestUtils::selectWhere($submissionData['submittedFiles'], function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'optionalFiles'
                && $submittedFileData['mimeType'] === 'application/pdf';
        });
        $this->assertCount(1, $optionalPdfFiles);

        $files = [
            'testFile' => [$uploadedTextFile2],
        ];
        $submittedFilesToDelete = [
            $testFiles[0]['identifier'],
            $optionalPdfFiles[0]['identifier'],
        ];

        $submissionData = $this->patchSubmission($submissionIdentifier, files: $files, submittedFilesToDelete: $submittedFilesToDelete);
        $this->assertTrue(Uuid::isValid($submissionData['identifier']));
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA, flags: JSON_THROW_ON_ERROR), $submissionData['dataFeedElement']);
        $this->assertEquals(Submission::SUBMISSION_STATE_SUBMITTED, $submissionData['submissionState']);
        $this->assertArrayHasKey('submittedFiles', $submissionData);
        $submittedFiles = $submissionData['submittedFiles'];
        $this->assertCount(2, $submittedFiles);

        $testFiles = TestUtils::selectWhere($submittedFiles, function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'testFile';
        });
        $this->assertCount(1, $testFiles);
        $submittedTextFile = $testFiles[0];
        $this->assertTrue(Uuid::isValid($submittedTextFile['identifier']));
        $this->assertEquals('testFile', $submittedTextFile['fileAttributeName']);
        $this->assertEquals(AbstractTestCase::TEXT_FILE_2_NAME, $submittedTextFile['fileName']);
        $this->assertEquals($uploadedTextFile2->getSize(), $submittedTextFile['fileSize']);
        $this->assertEquals('text/plain', $submittedTextFile['mimeType']);

        $optionalFiles = TestUtils::selectWhere($submittedFiles, function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'optionalFiles';
        });
        $this->assertCount(1, $optionalFiles);
        $submittedFile = $optionalFiles[0];
        $this->assertTrue(Uuid::isValid($submittedFile['identifier']));
        $this->assertEquals('optionalFiles', $submittedFile['fileAttributeName']);
        $this->assertEquals(AbstractTestCase::TEXT_FILE_2_NAME, $submittedFile['fileName']);
        $this->assertEquals($uploadedTextFile2->getSize(), $submittedFile['fileSize']);
        $this->assertEquals('text/plain', $submittedFile['mimeType']);
    }

    public function testPatchSubmissionWithFilesFileSchemaViolation(): void
    {
        $form = $this->createTestForm(dataFeedSchema: AbstractTestCase::TEST_FORM_SCHEMA_WITH_TEST_FILE);
        $formIdentifier = $form['identifier'];

        $uploadedTextFile = new UploadedFile(AbstractTestCase::TEXT_FILE_PATH, AbstractTestCase::TEXT_FILE_NAME);

        $files = [
            'testFile' => $uploadedTextFile,
        ];
        $submissionData = $this->postSubmission($formIdentifier, files: $files);

        // we now remove the required file attribute 'testFile'
        $submissionIdentifier = $submissionData['identifier'];
        $testFiles = TestUtils::selectWhere($submissionData['submittedFiles'], function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'testFile';
        });
        $this->assertCount(1, $testFiles);

        $submittedFilesToDelete = [
            'testFile' => $testFiles[0]['identifier'],
        ];

        $errorData = $this->patchSubmission($submissionIdentifier, submittedFilesToDelete: $submittedFilesToDelete,
            expectedStatusCode: Response::HTTP_BAD_REQUEST);
        $this->assertEquals('formalize:submission-submitted-files-invalid-schema', $errorData['relay:errorId']);
    }

    protected function createTestForm(
        string $name = self::TEST_FORM_NAME,
        ?string $dataFeedSchema = AbstractTestCase::TEST_FORM_SCHEMA,
        ?array $availableTags = AbstractTestCase::TEST_AVAILABLE_TAGS): array
    {
        $formData = [
            'name' => $name,
        ];
        if ($dataFeedSchema !== null) {
            $formData['dataFeedSchema'] = $dataFeedSchema;
        }
        if ($availableTags !== null) {
            $formData['availableTags'] = $availableTags;
        }
        $response = $this->testClient->postJson('/formalize/forms', $formData);
        $this->postRequestCleanup();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    protected function postSubmissionWithDeprecateFormatJsonLd(string $formIdentifier, array $data = self::TEST_DATA): array
    {
        try {
            $submissionData = [
                'form' => '/formalize/forms/'.$formIdentifier,
                'dataFeedElement' => json_encode($data, flags: JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT),
            ];
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Invalid data: '.$exception->getMessage());
        }

        $response = $this->testClient->postJson('/formalize/submissions', $submissionData);

        $this->postRequestCleanup();
        if ($response->getStatusCode() !== 201) {
            dump(json_decode($response->getContent(false), true));
        }
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    /**
     * @param array<string, UploadedFile|UploadedFile[]> $files
     */
    protected function postSubmission(string $formIdentifier, ?array $dataFeedElement = self::TEST_DATA,
        ?int $submissionState = Submission::SUBMISSION_STATE_SUBMITTED,
        ?array $tags = null, array $files = [],
        int $expectedStatusCode = Response::HTTP_CREATED): array
    {
        $requestOptions = [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
                'Accept' => 'application/ld+json',
            ],
            'extra' => [
                'files' => $files,
                'parameters' => [
                    'form' => '/formalize/forms/'.$formIdentifier,
                ],
            ],
        ];

        if ($dataFeedElement !== null) {
            try {
                $requestOptions['extra']['parameters']['dataFeedElement'] = json_encode($dataFeedElement, flags: JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
            } catch (\JsonException $exception) {
                throw new \RuntimeException('Failed to JSON encode data: '.$exception->getMessage());
            }
        }
        if ($submissionState !== null) {
            $requestOptions['extra']['parameters']['submissionState'] = $submissionState;
        }
        if ($tags !== null) {
            $requestOptions['extra']['parameters']['tags'] = json_encode($tags, flags: JSON_THROW_ON_ERROR);
        }
        if ($files !== []) {
            $requestOptions['extra']['files'] = $files;
        }

        $response = $this->testClient->request('POST', '/formalize/submissions', $requestOptions);
        $this->postRequestCleanup();
        if ($response->getStatusCode() !== $expectedStatusCode) {
            dump(json_decode($response->getContent(false), true));
        }
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    /**
     * @param array<string, UploadedFile|UploadedFile[]> $files
     * @param string[]                                   $submittedFilesToDelete
     */
    protected function patchSubmission(string $submissionIdentifier, ?array $dataFeedElement = null,
        int $submissionState = Submission::SUBMISSION_STATE_SUBMITTED, ?array $tags = null,
        array $files = [], array $submittedFilesToDelete = [],
        int $expectedStatusCode = Response::HTTP_OK): array
    {
        $requestOptions = [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
                'Accept' => 'application/ld+json',
            ],
        ];

        if ($dataFeedElement !== null) {
            try {
                $requestOptions['extra']['parameters']['dataFeedElement'] = json_encode($dataFeedElement, flags: JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
            } catch (\JsonException $exception) {
                throw new \RuntimeException('Failed to JSON encode data: '.$exception->getMessage());
            }
        }
        if ($submissionState !== null) {
            $requestOptions['extra']['parameters']['submissionState'] = $submissionState;
        }
        if ($tags !== null) {
            $requestOptions['extra']['parameters']['tags'] = json_encode($tags, flags: JSON_THROW_ON_ERROR);
        }
        if ($files !== []) {
            $requestOptions['extra']['files'] = $files;
        }
        foreach ($submittedFilesToDelete as $submittedFileIdentifier) {
            $requestOptions['extra']['parameters']['submittedFiles'][$submittedFileIdentifier] = 'null';
        }

        $response = $this->testClient->request('PATCH', '/formalize/submissions/'.$submissionIdentifier, $requestOptions);
        $this->postRequestCleanup();
        if ($response->getStatusCode() !== $expectedStatusCode) {
            dump(json_decode($response->getContent(false), true));
        }
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    protected function postRequestCleanup(): void
    {
        TestUtils::cleanupRequestCaches($this->testClient->getContainer());
    }
}
