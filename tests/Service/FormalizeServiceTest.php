<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
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
        $connection->executeQuery('CREATE TABLE formalize_forms (identifier VARCHAR(50) PRIMARY KEY, name VARCHAR(256) NOT NULL, date_created DATETIME)');
        $connection->executeQuery('CREATE TABLE formalize_submissions (identifier VARCHAR(50) NOT NULL, data_feed_element TEXT NOT NULL, date_created DATETIME NOT NULL, form VARCHAR(255) NOT NULL, PRIMARY KEY(identifier))');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->entityManager = new EntityManager($connection, $config);
        $this->formalizeService = new FormalizeService($this->entityManager, $eventDispatcher);
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     */
    public function testAddForm()
    {
        $form = new Form();
        $form->setName('Test Name');
        $form = $this->formalizeService->addForm($form);

        $this->assertSame('Test Name', $form->getName());
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

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testGetSubmissions()
    {
        $form = $this->addForm();
        $submission1 = $this->addSubmission($form, '{"foo": "bar"}');
        $submission2 = $this->addSubmission($form, '{"foo": "baz"}');

        $submissions = $this->formalizeService->getSubmissions(1, 3);
        $this->assertCount(2, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());
        $this->assertEquals($submission2->getIdentifier(), $submissions[1]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissions(1, 1);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissions(2, 1);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission2->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissions(2, 2);
        $this->assertCount(0, $submissions);

        $this->removeSubmission($submission1, false);
        $this->removeSubmission($submission2, true);
    }

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

        $submissions = $this->formalizeService->getSubmissions(1, 3, ['formId' => $form1->getIdentifier()]);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissions(1, 3, ['formId' => $form2->getIdentifier()]);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission2->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissions(1, 3, ['formId' => 'foo']);
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
    public function testSubmissionInvalidJsonError()
    {
        $form = $this->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('foo');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
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
    public function testSubmissionInvalidDataFeedElementKeysError()
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
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('doesn\'t match with the pevious submissions', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json-keys', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
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
     */
    private function addForm(string $name = 'Test Form'): Form
    {
        $form = new Form();
        $form->setName($name);
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
