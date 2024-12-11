<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\CoreBundle\TestUtils\TestClient;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends ApiTestCase
{
    private const TEST_DATA_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'firstname' => [
                'type' => 'string',
            ],
            'lastname' => [
                'type' => 'string',
            ],
        ],
        'required' => ['firstname', 'lastname'],
        'additionalProperties' => false,
    ];
    private const TEST_FORM_NAME = 'Test Form';
    private const TEST_DATA = [
        'firstname' => 'Joni',
        'lastname' => 'Doe',
    ];
    private const TEST_USER_IDENTIFIER = TestClient::TEST_USER_IDENTIFIER;
    private const ANOTHER_TEST_USER_IDENTIFIER = TestClient::TEST_USER_IDENTIFIER.'_2';

    private ?TestClient $testClient = null;
    private ?TestEntityManager $testEntityManager = null;

    protected function setUp(): void
    {
        $this->testClient = new TestClient(self::createClient());
        $this->login();
        AuthorizationTest::setUp($this->testClient->getContainer());
        $this->testEntityManager = new TestEntityManager($this->testClient->getContainer());
        // the following allows multiple requests in one test:
        $this->testClient->getClient()->disableReboot();
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
        $this->assertEquals(json_encode(self::TEST_DATA_SCHEMA, flags: JSON_THROW_ON_ERROR), $formData['dataFeedSchema']);
    }

    // fails on dev for unknown reason
    public function testCreateFormForbidden(): void
    {
        $this->login(userAttributes: ['MAY_CREATE_FORMS' => false]);
        $data = [
            'name' => self::TEST_FORM_NAME,
            'dataFeedSchema' => json_encode(self::TEST_DATA_SCHEMA, flags: JSON_THROW_ON_ERROR),
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
        $this->assertEquals(json_encode(self::TEST_DATA_SCHEMA, flags: JSON_THROW_ON_ERROR), $formData['dataFeedSchema']);
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
        $this->assertEquals(json_encode(self::TEST_DATA_SCHEMA, flags: JSON_THROW_ON_ERROR),
            $updatedFormData['dataFeedSchema']);
    }

    public function testPatchFormBackwardCompatibility(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        $updatedDataSchema = self::TEST_DATA_SCHEMA;
        $updatedDataSchema['properties'] = [
            'birthday' => [
                'type' => 'string',
            ],
        ];

        $newData = [
            'dataFeedSchema' => json_encode($updatedDataSchema),
        ];

        $response = $this->testClient->patchJson('/formalize/forms/'.$formIdentifier, $newData);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $updatedFormData = json_decode($response->getContent(false), true);
        $this->assertEquals($formIdentifier, $updatedFormData['identifier']);
        $this->assertEquals(self::TEST_FORM_NAME, $updatedFormData['name']);
        $this->assertEquals(json_encode($updatedDataSchema), $updatedFormData['dataFeedSchema']);
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

    // fails on dev for unknown reason
    public function testCreateSubmissionForbidden(): void
    {
        $form = $this->createTestForm();
        $formIdentifier = $form['identifier'];

        // log in user other than the creator of the form
        $this->login(self::ANOTHER_TEST_USER_IDENTIFIER);

        $submissionData = [
            'form' => '/formalize/forms/'.$formIdentifier,
            'dataFeedElement' => '{}',
        ];
        $response = $this->testClient->postJson('/formalize/submissions', $submissionData);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

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
            'firstname' => 'John',
            'lastname' => 'Smith',
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

    protected function createTestForm(string $name = self::TEST_FORM_NAME, ?array $dataFeedSchema = self::TEST_DATA_SCHEMA): array
    {
        try {
            $data = [
                'name' => $name,
                'dataFeedSchema' => $dataFeedSchema ? json_encode($dataFeedSchema, flags: JSON_THROW_ON_ERROR) : null,
            ];
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Invalid data schema: '.$exception->getMessage());
        }

        $response = $this->testClient->postJson('/formalize/forms', $data);
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
            dump($response->getContent(false));
        }
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        return json_decode($response->getContent(false), true);
    }

    private function login(
        string $userIdentifier = self::TEST_USER_IDENTIFIER,
        array $userAttributes = ['MAY_CREATE_FORMS' => true]): void
    {
        $this->testClient->setUpUser($userIdentifier, userAttributes: $userAttributes);
    }

    private function postRequestCleanup(): void
    {
        TestUtils::cleanupRequestCaches($this->testClient->getContainer());
    }
}
