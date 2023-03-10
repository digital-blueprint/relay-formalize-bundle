<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={
 *         "get" = {
 *             "path" = "/formalize/submissions",
 *             "security" = "is_granted('IS_AUTHENTICATED_FULLY')",
 *             "openapi_context" = {
 *                 "tags" = {"Formalize"},
 *             },
 *         },
 *         "post" = {
 *             "method" = "POST",
 *             "path" = "/formalize/submissions",
 *             "openapi_context" = {
 *                 "tags" = {"Formalize"},
 *                 "requestBody" = {
 *                     "content" = {
 *                         "application/json" = {
 *                             "schema" = {"type" = "object"},
 *                             "example" = {"dataFeedElement" = "{""firstname"": ""john"", ""lastname"": ""Doe""}", "form" = "my-form"},
 *                         }
 *                     }
 *                 },
 *             },
 *         }
 *     },
 *     itemOperations={
 *         "get" = {
 *             "security" = "is_granted('IS_AUTHENTICATED_FULLY')",
 *             "path" = "/formalize/submissions/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Formalize"}
 *             },
 *         }
 *     },
 *     iri="https://schema.org/DataFeed",
 *     shortName="FormalizeSubmission",
 *     normalizationContext={
 *         "groups" = {"FormalizeSubmission:output"},
 *         "jsonld_embed_context" = true
 *     },
 *     denormalizationContext={
 *         "groups" = {"FormalizeSubmission:input"},
 *         "jsonld_embed_context" = true
 *     }
 * )
 */
class Submission
{
    /**
     * @ApiProperty(identifier=true)
     */
    private $identifier;

    /**
     * @ApiProperty(iri="https://schema.org/DataFeed")
     * @Groups({"FormalizeSubmission:output", "FormalizeSubmission:input"})
     *
     * @var string
     */
    private $dataFeedElement;

    /**
     * @ApiProperty(iri="https://schema.org/Text")
     * @Groups({"FormalizeSubmission:output", "FormalizeSubmission:input"})
     *
     * @var string
     */
    private $form;

    /**
     * @ApiProperty(iri="https://schema.org/dateCreated")
     * @Groups({"FormalizeSubmission:output"})
     *
     * @var \DateTime
     */
    private $dateCreated;

    public function getDataFeedElement(): string
    {
        return $this->dataFeedElement;
    }

    /**
     * @throws \JsonException
     */
    public function getDataFeedElementDecoded(): array
    {
        return json_decode($this->dataFeedElement, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws ApiError
     */
    public function compareDataFeedElementKeys(FormalizeService $formalizeService): void
    {
        $formName = $this->getForm();

        try {
            $submission = $formalizeService->getOneSubmissionByForm($formName);
        } catch (ApiError $exception) {
            return; // It's a new form, so it's okay to create a new scheme
        }

        $dataFeedElementPrev = $submission->dataFeedElement;

        try {
            $dataFeedElementPrev = json_decode($dataFeedElementPrev, true, 512, JSON_THROW_ON_ERROR);
            $dataFeedElement = json_decode($this->dataFeedElement, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY, 'The dataFeedElement doesn\'t contain valid json!', 'formalize:submission-invalid-json');
        }

        $diffKey = array_diff_key($dataFeedElementPrev, $dataFeedElement);

        // If there is a diff between old and new scheme throw an error
        if (!empty($diffKey)) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY, 'The dataFeedElement doesn\'t match with the pevious submissions of the form: \''.$formName.'\' (the keys must correspond to scheme: \''.implode("', '", array_keys($dataFeedElementPrev)).'\')', 'formalize:submission-invalid-json-keys');
        }
    }

    public function setDataFeedElement(string $dataFeedElement): void
    {
        $this->dataFeedElement = $dataFeedElement;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getForm(): string
    {
        return $this->form;
    }

    public function setForm(string $form): void
    {
        $this->form = $form;
    }

    public function setDateCreated(\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateCreated(): \DateTime
    {
        return $this->dateCreated;
    }

    public static function fromSubmissionPersistence(SubmissionPersistence $submissionPersistence): Submission
    {
        $submission = new Submission();
        $submission->setIdentifier($submissionPersistence->getIdentifier());
        $submission->setForm($submissionPersistence->getForm());
        $submission->setDataFeedElement($submissionPersistence->getDataFeedElement());
        $submission->setDateCreated($submissionPersistence->getDateCreated());

        return $submission;
    }

    /**
     * @param SubmissionPersistence[] $submissionPersistences
     *
     * @return Submission[]
     */
    public static function fromSubmissionPersistences(array $submissionPersistences): array
    {
        $submissions = [];

        foreach ($submissionPersistences as $submissionPersistence) {
            $submissions[] = self::fromSubmissionPersistence($submissionPersistence);
        }

        return $submissions;
    }
}
