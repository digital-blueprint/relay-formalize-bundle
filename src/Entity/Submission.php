<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
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
