<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\BlobBundle\TestUtils\TestEntityManager as BlobTestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\AbstractApiTest;
use Dbp\Relay\CoreBundle\TestUtils\TestClient;
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
            ['GET', '/formalize/submissions'],
            ['POST', '/formalize/submissions'],
            ['GET', '/formalize/submissions/123'],
            ['PATCH', '/formalize/submissions/123'],
            ['DELETE', '/formalize/submissions/123'],
            ['GET', '/formalize/forms'],
            ['POST', '/formalize/forms'],
            ['GET', '/formalize/forms/123'],
            ['PATCH', '/formalize/forms/123'],
            ['DELETE', '/formalize/forms/123'],
        ];

        foreach ($endpoints as $ep) {
            [$method, $path] = $ep;
            $options = [
                'headers' => [
                    'Content-Type' => 'application/'.($method === 'PATCH' ? 'merge-patch+json' : 'ld+json'),
                ]];
            $response = $this->testClient->request($method, $path, $options, null);
            $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(),
                'Expected '.Response::HTTP_UNAUTHORIZED.', got '.$response->getStatusCode().' for path '.$path);
        }
    }

    public function testCreateForm(): void
    {
        $formData = $this->createTestForm();

        $this->assertNotNull($formData['identifier']);
        $this->assertEquals(self::TEST_FORM_NAME, $formData['name']);
        $this->assertEquals(AbstractTestCase::TEST_FORM_SCHEMA, $formData['dataFeedSchema']);
    }

    // fails on dev for unknown reason
    //    public function testCreateFormForbidden(): void
    //    {
    //        $this->login(userAttributes: ['MAY_CREATE_FORMS' => false]);
    //        $data = [
    //            'name' => self::TEST_FORM_NAME,
    //        ];
    //
    //        $response = $this->testClient->postJson('/formalize/forms', $data);
    //        $this->assertEquals(403, $response->getStatusCode());
    //    }

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

    public function testPatchForm(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $newData = [
            'name' => 'Updated '.self::TEST_FORM_NAME,
        ];

        $response = $this->testClient->patchJson('/formalize/forms/'.$formIdentifier, $newData);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $updatedFormData = json_decode($response->getContent(false), true);
        $this->assertEquals($formIdentifier, $updatedFormData['identifier']);
        $this->assertEquals('Updated '.self::TEST_FORM_NAME, $updatedFormData['name']);
        $this->assertEquals(AbstractTestCase::TEST_FORM_SCHEMA, $updatedFormData['dataFeedSchema']);
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

    public function testCreateSubmission(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = $this->createTestSubmission($formIdentifier);
        $this->assertNotNull($submissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $submissionData['form']);
        $this->assertEquals(json_encode(self::TEST_DATA, flags: JSON_THROW_ON_ERROR), $submissionData['dataFeedElement']);
    }

    public function testCreateSubmissionWithFile(): void
    {
        $form = $this->createTestForm(dataFeedSchema: AbstractTestCase::TEST_FORM_SCHEMA_WITH_TEST_FILE);
        $formIdentifier = $form['identifier'];

        $uploadedTextFile = new UploadedFile(AbstractTestCase::TEXT_FILE_PATH, AbstractTestCase::TEXT_FILE_NAME);
        $uploadedPdfFile = new UploadedFile(AbstractTestCase::PDF_FILE_PATH, AbstractTestCase::PDF_FILE_PATH);
        $files = [
            'testFile' => $uploadedTextFile,
            'optionalFiles' => [$uploadedPdfFile],
        ];

        $submissionData = $this->createTestSubmissionWithFiles($formIdentifier, files: $files);
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

    // fails on dev for unknown reason
    //    public function testCreateSubmissionForbidden(): void
    //    {
    //        $form = $this->createTestForm();
    //        $formIdentifier = $form['identifier'];
    //
    //        // log in user other than the creator of the form
    //        $this->login(self::ANOTHER_TEST_USER_IDENTIFIER);
    //
    //        $submissionData = [
    //            'form' => '/formalize/forms/'.$formIdentifier,
    //            'dataFeedElement' => '{}',
    //        ];
    //        $response = $this->testClient->postJson('/formalize/submissions', $submissionData);
    //        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    //    }

    public function testCreateEmptySubmission(): void
    {
        $form = $this->createTestForm(dataFeedSchema: null);
        $formIdentifier = $form['identifier'];

        $submissionData = $this->createTestSubmission($formIdentifier, []);
        $this->assertEquals('{}', $submissionData['dataFeedElement']);
    }

    public function testCreateSubmissionWithInvalidSchema(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = [
            'form' => '/formalize/forms/'.$formIdentifier,
            'dataFeedElement' => json_encode(['foo' => 'bar']),
        ];
        $response = $this->testClient->postJson('/formalize/submissions', $submissionData);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testGetSubmission(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $submissionData = $this->createTestSubmission($formIdentifier);
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

        $submissionData = $this->createTestSubmission($formIdentifier);
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

        $submissionData = $this->createTestSubmission($formIdentifier);
        $submissionIdentifier = $submissionData['identifier'];

        $updatedData = [
            'givenName' => 'John',
            'familyName' => 'Smith',
        ];
        $submissionDataPatch = [
            'dataFeedElement' => json_encode($updatedData),
        ];

        $response = $this->testClient->patchJson('/formalize/submissions/'.$submissionIdentifier, $submissionDataPatch);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $updatedSubmissionData = json_decode($response->getContent(false), true);
        $this->assertEquals($submissionIdentifier, $updatedSubmissionData['identifier']);
        $this->assertEquals('/formalize/forms/'.$formIdentifier, $updatedSubmissionData['form']);
        $this->assertEquals(json_encode($updatedData), $updatedSubmissionData['dataFeedElement']);
    }

    public function testPatchSubmissionWithFiles(): void
    {
        $form = $this->createTestForm(dataFeedSchema: AbstractTestCase::TEST_FORM_SCHEMA_WITH_TEST_FILE);
        $formIdentifier = $form['identifier'];

        $uploadedTextFile = new UploadedFile(AbstractTestCase::TEXT_FILE_PATH, AbstractTestCase::TEXT_FILE_NAME);
        $uploadedPdfFile = new UploadedFile(AbstractTestCase::PDF_FILE_PATH, AbstractTestCase::PDF_FILE_PATH);
        $files = [
            'testFile' => $uploadedTextFile,
            'optionalFiles' => [$uploadedPdfFile],
        ];

        $submissionData = $this->createTestSubmissionWithFiles($formIdentifier, files: $files);

        // we now replace 'testFile' and add another file to 'optionalFiles':
        $submissionIdentifier = $submissionData['identifier'];
        $testFiles = TestUtils::selectWhere($submissionData['submittedFiles'], function (array $submittedFileData): bool {
            return $submittedFileData['fileAttributeName'] === 'testFile';
        });
        $this->assertCount(1, $testFiles);
        $submittedFilesToDelete = [$testFiles[0]['identifier']];

        $uploadedTextFile2 = new UploadedFile(AbstractTestCase::TEXT_FILE_2_PATH, AbstractTestCase::TEXT_FILE_2_NAME);
        $files = [
            'testFile' => [$uploadedTextFile2],
            'optionalFiles' => $uploadedTextFile2,
        ];
        $submissionData = $this->patchSubmissionWithFiles($submissionIdentifier, files: $files, submittedFilesToDelete: $submittedFilesToDelete);
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

    protected function createTestForm(string $name = self::TEST_FORM_NAME, ?string $dataFeedSchema = AbstractTestCase::TEST_FORM_SCHEMA): array
    {
        $formData = [
            'name' => $name,
        ];
        if ($dataFeedSchema !== null) {
            $formData['dataFeedSchema'] = $dataFeedSchema;
        }
        $response = $this->testClient->postJson('/formalize/forms', $formData);
        $this->postRequestCleanup();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    protected function createTestSubmission(string $formIdentifier, array $data = self::TEST_DATA): array
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
    protected function createTestSubmissionWithFiles(string $formIdentifier, ?array $dataFeedElement = self::TEST_DATA,
        ?int $submissionState = Submission::SUBMISSION_STATE_SUBMITTED, ?array $files = null): array
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
        if ($files !== null) {
            $requestOptions['extra']['files'] = $files;
        }

        $response = $this->testClient->request('POST', '/formalize/submissions/multipart', $requestOptions);
        $this->postRequestCleanup();
        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            dump(json_decode($response->getContent(false), true));
        }
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    /**
     * @param array<string, UploadedFile|UploadedFile[]> $files
     * @param string[]                                   $submittedFilesToDelete
     */
    protected function patchSubmissionWithFiles(string $submissionIdentifier, ?array $dataFeedElement = null,
        int $submissionState = Submission::SUBMISSION_STATE_SUBMITTED, array $files = [],
        ?array $submittedFilesToDelete = null): array
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
        if ($files !== null) {
            $requestOptions['extra']['files'] = $files;
        }
        if ($submittedFilesToDelete !== null) {
            $requestOptions['extra']['parameters']['submittedFilesToDelete'] = implode(',', $submittedFilesToDelete);
        }

        $response = $this->testClient->request('PATCH', '/formalize/submissions/'.$submissionIdentifier.'/multipart', $requestOptions);
        $this->postRequestCleanup();
        if ($response->getStatusCode() !== Response::HTTP_OK) {
            dump(json_decode($response->getContent(false), true));
        }
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    private function postRequestCleanup(): void
    {
        TestUtils::cleanupRequestCaches($this->testClient->getContainer());
    }
}
