<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\TestUtils;

use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;

class TestEntityManager
{
    public const DEFAULT_FORM_NAME = 'Test Form';

    private EntityManager $entityManager;

    public function __construct(KernelInterface $kernel)
    {
        if ('test' !== $kernel->getEnvironment()) {
            throw new \RuntimeException('Execution only in Test environment possible!');
        }

        //    $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager('dbp_relay_formalize_bundle');
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);

        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public function addForm(string $name = self::DEFAULT_FORM_NAME, ?string $dataFeedSchema = null): Form
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
