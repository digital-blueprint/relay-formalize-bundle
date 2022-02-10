<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Dbp\Relay\FormalizeBundle\Controller\LoggedInOnly;
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
     * @ApiProperty(iri="https://schema.org/name")
     * @Groups({"FormalizeSubmission:output", "FormalizeSubmission:input"})
     *
     * @var string
     */
    private $data;

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public static function fromSubmissionPersistence(SubmissionPersistence $submissionPersistence): Submission
    {
        $submission = new Submission();
        $submission->setIdentifier($submissionPersistence->getIdentifier());
        $submission->setData($submissionPersistence->getData());

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
