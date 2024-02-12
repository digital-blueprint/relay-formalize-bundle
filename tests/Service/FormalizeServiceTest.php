<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use Dbp\Relay\CoreBundle\User\UserAttributeMuxer;
use Dbp\Relay\CoreBundle\User\UserAttributeProviderProvider;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class FormalizeServiceTest extends WebTestCase
{
    /** @var FormalizeService */
    private $formalizeService;

    /** @var EntityManager */
    private $entityManager;

    /**
     * @throws Exception
     * @throws ORMException
     */
    protected function setUp(): void
    {
        $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.'/../../src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $connection = DriverManager::getConnection( // EntityManager::create(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ], $config
        );
        $connection->executeQuery('CREATE TABLE formalize_forms (identifier VARCHAR(50) PRIMARY KEY, name VARCHAR(256) NOT NULL, date_created DATETIME, data_feed_schema LONGTEXT, availability_starts DATETIME, availability_ends DATETIME, creator_id VARCHAR(50), read_form_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', write_form_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', add_submissions_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', read_submissions_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', write_submissions_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\')');
        $connection->executeQuery('CREATE TABLE formalize_submissions (identifier VARCHAR(50) NOT NULL, data_feed_element TEXT NOT NULL, date_created DATETIME NOT NULL, form VARCHAR(255) NOT NULL, creator_id VARCHAR(50), PRIMARY KEY(identifier))');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->entityManager = new EntityManager($connection, $config);
        $authorizationService = new AuthorizationService();
        $authorizationService->__injectUserSessionAndUserAttributeMuxer(
            new TestUserSession('testuser'),
            new UserAttributeMuxer(new UserAttributeProviderProvider([]), new EventDispatcher()));
        $this->formalizeService = new FormalizeService($this->entityManager, $eventDispatcher, $authorizationService);
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     */
    public function testAddForm()
    {
        $testName = 'Test Name';

        $form = new Form();
        $form->setName($testName);
        $form = $this->formalizeService->addForm($form);

        $this->assertSame($testName, $form->getName());
        $this->assertNotEmpty($form->getIdentifier());
        $this->assertNotEmpty($form->getDateCreated());

        $formPersistence = $this->entityManager->getRepository(Form::class)
            ->findOneBy(['identifier' => $form->getIdentifier()]);
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());

        $this->removeForm($formPersistence);
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
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
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     */
    public function testAddFormWithDataFeedSchema()
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

        $form = new Form();
        $form->setName('Test Name');
        $form->setDataFeedSchema($testDataFeedSchema);

        $form = $this->formalizeService->addForm($form);

        $this->assertSame($testDataFeedSchema, $form->getDataFeedSchema());

        $formPersistence = $this->entityManager->getRepository(Form::class)
            ->findOneBy(['identifier' => $form->getIdentifier()]);
        $this->assertSame($form->getDataFeedSchema(), $formPersistence->getDataFeedSchema());

        $this->removeForm($formPersistence);
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddFormDataFeedErrorInvalidJson()
    {
        // invalid JSON
        $testDataFeedSchema = '{
            "type" = "object"
        }';

        $form = new Form();
        $form->setName('Test Name');
        $form->setDataFeedSchema($testDataFeedSchema);

        try {
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('\'dataFeedSchema\' is not a valid JSON schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-invalid-data-feed-schema', $apiError->getErrorId());
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddFormDataFeedErrorInvalidJsonSchema()
    {
        // invalid JSON schema
        $testDataFeedSchema = '{
            "type": "foo"
        }';

        $form = new Form();
        $form->setName('Test Name');
        $form->setDataFeedSchema($testDataFeedSchema);

        try {
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('\'dataFeedSchema\' is not a valid JSON schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-invalid-data-feed-schema', $apiError->getErrorId());
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     */
    public function testUpdateForm()
    {
        $form = $this->addForm('Test Name');
        $formId = $form->getIdentifier();
        $dataCreated = $form->getDateCreated();

        $form->setName('Updated Name');
        $this->formalizeService->updateForm($form);

        $formPersistence = $this->entityManager->getRepository(Form::class)
            ->findOneBy(['identifier' => $formId]);
        $this->assertSame($formId, $formPersistence->getIdentifier());
        $this->assertSame('Updated Name', $formPersistence->getName());
        $this->assertSame($dataCreated, $formPersistence->getDateCreated());

        $this->removeForm($formPersistence);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testGetForm()
    {
        $form = $this->addForm('Test Name');
        $this->assertSame('Test Name', $form->getName());

        $formPersistence = $this->formalizeService->getForm($form->getIdentifier());
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());

        $this->removeForm($formPersistence);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
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
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function testGetForms()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();

        $forms = $this->formalizeService->getForms(1, 3);
        $this->assertCount(2, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());
        $this->assertEquals($form2->getIdentifier(), $forms[1]->getIdentifier());

        $forms = $this->formalizeService->getForms(1, 1);
        $this->assertCount(1, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());

        $forms = $this->formalizeService->getForms(2, 1);
        $this->assertCount(1, $forms);
        $this->assertEquals($form2->getIdentifier(), $forms[0]->getIdentifier());

        $forms = $this->formalizeService->getForms(2, 2);
        $this->assertCount(0, $forms);

        $this->removeForm($form1);
        $this->removeForm($form2);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testRemoveForm()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->assertNotNull($this->entityManager->getRepository(Form::class)
            ->findOneBy(['identifier' => $form->getIdentifier()]));
        $this->assertNotNull($this->entityManager->getRepository(Submission::class)
            ->findOneBy(['identifier' => $submission->getIdentifier()]));

        $this->formalizeService->removeForm($form);

        // form and submission must be removed
        $this->assertNull($this->entityManager->getRepository(Form::class)
            ->findOneBy(['identifier' => $form->getIdentifier()]));
        $this->assertNull($this->entityManager->getRepository(Submission::class)
            ->findOneBy(['identifier' => $submission->getIdentifier()]));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws NotSupported
     */
    public function testAddSubmission()
    {
        $form = $this->addForm();

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $submissionPersistence = $this->entityManager->getRepository(Submission::class)
            ->findOneBy(['identifier' => $submission->getIdentifier()]);
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());

        $formPersistence = $submissionPersistence->getForm();
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());

        $this->removeSubmission($submission, true);
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     * @throws \JsonException
     */
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

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionDataFeedElementMissingError()
    {
        try {
            $submission = new Submission();
            $submission->setForm($this->addForm());
            $this->formalizeService->addSubmission($submission);
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('field \'dataFeedElement\' is required', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-field-missing', $apiError->getErrorId());
            $this->assertEquals('dataFeedElement', $apiError->getErrorDetails()[0]);
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws NotSupported
     */
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

        $form = $this->addForm('people', $testDataFeedSchema);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $submissionPersistence = $this->entityManager->getRepository(Submission::class)
            ->findOneBy(['identifier' => $submission->getIdentifier()]);
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());

        $formPersistence = $submissionPersistence->getForm();
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());

        $this->removeSubmission($submission, true);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testGetSubmission()
    {
        $submission = $this->addSubmission(null, '{"foo": "bar"}');

        $submissionPersistence = $this->formalizeService->getSubmissionByIdentifier($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());

        $this->removeSubmission($submission, true);
    }

    // Get all submissions without form identifier is currently? disallowed
    //    /**
    //     * @throws OptimisticLockException
    //     * @throws ORMException
    //     * @throws \JsonException
    //     */
    //    public function testGetSubmissions()
    //    {
    //        $form = $this->addForm();
    //        $submission1 = $this->addSubmission($form, '{"foo": "bar"}');
    //        $submission2 = $this->addSubmission($form, '{"foo": "baz"}');
    //
    //        $submissions = $this->formalizeService->getSubmissions(1, 3);
    //        $this->assertCount(2, $submissions);
    //        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());
    //        $this->assertEquals($submission2->getIdentifier(), $submissions[1]->getIdentifier());
    //
    //        $submissions = $this->formalizeService->getSubmissions(1, 1);
    //        $this->assertCount(1, $submissions);
    //        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());
    //
    //        $submissions = $this->formalizeService->getSubmissions(2, 1);
    //        $this->assertCount(1, $submissions);
    //        $this->assertEquals($submission2->getIdentifier(), $submissions[0]->getIdentifier());
    //
    //        $submissions = $this->formalizeService->getSubmissions(2, 2);
    //        $this->assertCount(0, $submissions);
    //
    //        $this->removeSubmission($submission1, false);
    //        $this->removeSubmission($submission2, true);
    //    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testGetSubmissionsByFormId()
    {
        $form1 = $this->addForm();
        $submission1 = $this->addSubmission($form1, '{"foo": "bar"}');
        $form2 = $this->addForm();
        $submission2 = $this->addSubmission($form2, '{"foo": "baz"}');

        $submissions = $this->formalizeService->getSubmissions(1, 3, ['formIdentifier' => $form1->getIdentifier()]);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissions(1, 3, ['formIdentifier' => $form2->getIdentifier()]);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission2->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissions(1, 3, ['formIdentifier' => 'foo']);
        $this->assertCount(0, $submissions);

        $this->removeSubmission($submission1, true);
        $this->removeSubmission($submission2, true);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testRemoveSubmission()
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);

        $this->assertNotNull($this->entityManager->getRepository(Submission::class)
            ->findOneBy(['identifier' => $submission->getIdentifier()]));

        $this->formalizeService->removeSubmission($submission);

        $this->assertNull($this->entityManager->getRepository(Submission::class)
            ->findOneBy(['identifier' => $submission->getIdentifier()]));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testRemoveFormSubmissions()
    {
        $form = $this->addForm();
        $this->addSubmission($form);
        $this->addSubmission($form);

        $this->assertCount(2, $this->entityManager->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));

        $this->formalizeService->removeAllFormSubmissions($form);

        $this->assertCount(0, $this->entityManager->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionInvalidJsonError()
    {
        $form = $this->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('foo');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }

        $this->removeForm($form);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionInvalidDataFeedElementKeysError()
    {
        $form = $this->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm($form);
        $this->formalizeService->addSubmission($sub);

        $sub = new Submission();
        $sub->setDataFeedElement('{"quux": "bar"}');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('doesn\'t match with the pevious submissions', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json-keys', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('doesn\'t match with the pevious submissions', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json-keys', $apiError->getErrorId());
        }

        $this->removeForm($form);
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

        $form = $this->addForm('people', $testDataFeedSchema);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName": "John"}'); // required property 'familyName' missing

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the JSON schema defined in the form!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        $submission->setDataFeedElement('{"familyName": "Doe", "email": "john@doe.com"}'); // undefined property 'email'

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the JSON schema defined in the form!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        $this->removeForm($form);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function addForm(string $name = 'Test Form', string $dataFeedSchema = null): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setDataFeedSchema($dataFeedSchema);
        $form->setIdentifier((string) Uuid::v4());
        $form->setDateCreated(new \DateTime('now'));
        $this->entityManager->persist($form);
        $this->entityManager->flush();

        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function removeForm(Form $form): void
    {
        $this->entityManager->remove($form);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    private function addSubmission(Form $form = null, string $jsonString = ''): Submission
    {
        if ($form === null) {
            $form = new Form();
            $form->setName('Test Form');
            $form->setIdentifier((string) Uuid::v4());
            $form->setDateCreated(new \DateTime('now'));
            $this->entityManager->persist($form);
        }
        $submission = new Submission();
        $submission->setIdentifier((string) Uuid::v4());
        $submission->setDateCreated(new \DateTime('now'));
        $submission->setForm($form);
        $submission->setDataFeedElement($jsonString);
        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        return $submission;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function removeSubmission(Submission $submission, bool $alsoRemoveForm): void
    {
        $form = $submission->getForm();
        $this->entityManager->remove($submission);
        if ($alsoRemoveForm) {
            $this->entityManager->remove($form);
        }
        $this->entityManager->flush();
    }
}
