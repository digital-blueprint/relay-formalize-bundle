<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\CoreBundle\Rest\Query\Filter\Filter;
use Dbp\Relay\CoreBundle\TestUtils\TestEntityManager as CoreTestEntityManager;
use Dbp\Relay\FormalizeBundle\DependencyInjection\DbpRelayFormalizeExtension;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

class TestEntityManager extends CoreTestEntityManager
{
    public const DEFAULT_FORM_NAME = 'Test Form';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, DbpRelayFormalizeExtension::FORMALIZE_ENTITY_MANAGER_ID);
    }

    public function addForm(
        string $name = self::DEFAULT_FORM_NAME,
        ?string $dataFeedSchema = null,
        ?bool $grantBasedSubmissionAuthorization = null,
        ?int $allowedSubmissionStates = null,
        ?array $actionsAllowedWhenSubmitted = null,
        ?int $maxNumSubmissionsPerCreator = null): Form
    {
        $form = new Form();
        $form->setIdentifier((string) Uuid::v4());
        $form->setDateCreated(new \DateTime('now'));
        $form->setName($name);
        if ($dataFeedSchema !== null) {
            $form->setDataFeedSchema($dataFeedSchema);
        }
        if ($grantBasedSubmissionAuthorization !== null) {
            $form->setGrantBasedSubmissionAuthorization($grantBasedSubmissionAuthorization);
        }
        if ($allowedSubmissionStates !== null) {
            $form->setAllowedSubmissionStates($allowedSubmissionStates);
        }
        if ($actionsAllowedWhenSubmitted !== null) {
            $form->setAllowedActionsWhenSubmittedPublic($actionsAllowedWhenSubmitted);
        }
        if ($maxNumSubmissionsPerCreator !== null) {
            $form->setMaxNumSubmissionsPerCreator($maxNumSubmissionsPerCreator);
        }

        $this->saveEntity($form);

        return $form;
    }

    public function updateForm(Form $form,
        ?string $name = null,
        ?string $dataFeedSchema = null,
        ?bool $grantBasedSubmissionAuthorization = null,
        ?int $allowedSubmissionStates = null,
        ?array $actionsAllowedWhenSubmitted = null,
        ?int $maxNumSubmissionsPerCreator = null)
    {
        if ($name !== null) {
            $form->setName($name);
        }
        if ($dataFeedSchema !== null) {
            $form->setDataFeedSchema($dataFeedSchema);
        }
        if ($grantBasedSubmissionAuthorization !== null) {
            $form->setGrantBasedSubmissionAuthorization($grantBasedSubmissionAuthorization);
        }
        if ($allowedSubmissionStates !== null) {
            $form->setAllowedSubmissionStates($allowedSubmissionStates);
        }
        if ($actionsAllowedWhenSubmitted !== null) {
            $form->setAllowedActionsWhenSubmittedPublic($actionsAllowedWhenSubmitted);
        }
        if ($maxNumSubmissionsPerCreator !== null) {
            $form->setMaxNumSubmissionsPerCreator($maxNumSubmissionsPerCreator);
        }
        $this->saveEntity($form);

        return $form;
    }

    public function removeForm(Form $form): void
    {
        $this->removeEntity($form);
    }

    public function addSubmission(
        ?Form $form = null,
        ?string $dataFeedElement = '{}',
        ?int $submissionState = null,
        ?string $creatorId = null): Submission
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
        $now = new \DateTime('now');
        $submission->setDateCreated($now);
        $submission->setDateLastModified($now);
        $submission->setCreatorId($creatorId);
        $submission->setForm($form);
        if ($dataFeedElement !== null) {
            $submission->setDataFeedElement($dataFeedElement);
        }
        if ($submissionState !== null) {
            $submission->setSubmissionState($submissionState);
        }

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

    /**
     * @return Submission[]
     */
    public function getSubmissions(?Filter $filter = null): array
    {
        return $this->getEntities(1, 1024, $filter, Submission::class);
    }

    public function getSubmittedFile(string $submittedFileIdentifier): ?SubmittedFile
    {
        return $this->getEntityByIdentifier($submittedFileIdentifier, SubmittedFile::class);
    }

    /**
     * @return SubmittedFile[]
     */
    public function getSubmittedFiles(?Filter $filter = null): array
    {
        return $this->getEntities(1, 1024, $filter, SubmittedFile::class);
    }
}
