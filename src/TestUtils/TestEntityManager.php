<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\TestUtils;

use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Uid\Uuid;

class TestEntityManager
{
    private EntityManager $entityManager;

    public static function create(): TestEntityManager
    {
        try {
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

            return new TestEntityManager(new EntityManager($connection, $config));
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function addForm(string $name = 'Test Form', ?string $dataFeedSchema = null): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setDataFeedSchema($dataFeedSchema);
        $form->setIdentifier((string) Uuid::v4());
        $form->setDateCreated(new \DateTime('now'));

        try {
            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function removeForm(Form $form): void
    {
        try {
            $this->entityManager->remove($form);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function addSubmission(?Form $form = null, string $jsonString = ''): Submission
    {
        try {
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
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $submission;
    }

    public function removeSubmission(Submission $submission, bool $alsoRemoveForm): void
    {
        try {
            $form = $submission->getForm();
            $this->entityManager->remove($submission);
            if ($alsoRemoveForm) {
                $this->entityManager->remove($form);
            }
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getForm(string $formIdentifier): ?Form
    {
        try {
            return $this->entityManager->getRepository(Form::class)
                ->findOneBy(['identifier' => $formIdentifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    public function getSubmission(string $submissionIdentifier): ?Submission
    {
        try {
            return $this->entityManager->getRepository(Submission::class)
                ->findOneBy(['identifier' => $submissionIdentifier]);
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }
}
