<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\TestEntityManager as CoreTestEntityManager;
use Dbp\Relay\FormalizeBundle\DependencyInjection\DbpRelayFormalizeExtension;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

class TestEntityManager extends CoreTestEntityManager
{
    public const DEFAULT_FORM_NAME = 'Test Form';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, DbpRelayFormalizeExtension::FORMALIZE_ENTITY_MANAGER_ID);
    }

    /**
     * @throws \JsonException
     */
    public function addForm(string $name = self::DEFAULT_FORM_NAME, ?string $dataFeedSchema = null, bool $submissionLevelAuthorization = false): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setDataFeedSchema($dataFeedSchema);
        $form->setIdentifier((string) Uuid::v4());
        $form->setSubmissionLevelAuthorization($submissionLevelAuthorization);
        $form->setDateCreated(new \DateTime('now'));

        $this->saveEntity($form);

        return $form;
    }

    public function removeForm(Form $form): void
    {
        $this->removeEntity($form);
    }

    /**
     * @throws \JsonException
     */
    public function addSubmission(?Form $form = null, string $jsonString = '{}'): Submission
    {
        if ($form === null) {
            $form = new Form();
            $form->setName('Test Form');
            $form->setIdentifier((string) Uuid::v4());
            $form->setDateCreated(new \DateTime('now'));

            $this->saveEntity($form);
        }
        $submission = new Submission();
        $submission->setIdentifier((string) Uuid::v4());
        $submission->setDateCreated(new \DateTime('now'));
        $submission->setForm($form);
        $submission->setDataFeedElement($jsonString);

        $this->saveEntity($submission);

        return $submission;
    }

    public function removeSubmission(Submission $submission, bool $alsoRemoveForm): void
    {
        $form = $submission->getForm();
        $this->removeEntity($submission);
        if ($alsoRemoveForm) {
            $this->removeEntity($form);
        }
    }

    public function getForm(string $formIdentifier): ?Form
    {
        return $this->getEntityByIdentifier($formIdentifier, Form::class);
    }

    public function getSubmission(string $submissionIdentifier): ?Submission
    {
        return $this->getEntityByIdentifier($submissionIdentifier, Submission::class);
    }
}
