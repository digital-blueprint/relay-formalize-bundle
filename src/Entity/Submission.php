<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\FormalizeBundle\Rest\PatchSubmissionController;
use Dbp\Relay\FormalizeBundle\Rest\PostSubmissionController;
use Dbp\Relay\FormalizeBundle\Rest\RemoveAllFormSubmissionsController;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProcessor;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Table(name: 'formalize_submissions')]
#[ORM\Entity]
#[ApiResource(
    shortName: 'FormalizeSubmission',
    types: ['https://schema.org/DataFeed'],
    operations: [
        new Get(
            uriTemplate: '/formalize/submissions/{identifier}',
            openapi: new Operation(
                tags: ['Formalize']
            ),
            normalizationContext: [
                'groups' => ['FormalizeSubmission:output', 'FormalizeSubmittedFile:output', 'FormalizeSubmittedFile:file_info_output'],
                'jsonld_embed_context' => true,
            ],
            provider: SubmissionProvider::class
        ),
        new GetCollection(
            uriTemplate: '/formalize/submissions',
            openapi: new Operation(
                tags: ['Formalize'],
                summary: 'Retrieves the collection of FormalizeSubmission resources for the specified FormalizeForm resource.',
                parameters: [
                    new Parameter(
                        name: 'formIdentifier',
                        in: 'query',
                        description: 'The identifier of the FormalizeForm resource to get submissions for',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'outputValidation',
                        in: 'query',
                        description: <<<DESC
                            The output validation filter to apply:
                            * NONE: Don't apply an output validation filter (default)
                            * KEYS: Only return submissions whose keys match those of the form schema
                            DESC,
                        required: false,
                        schema: [
                            'type' => 'string',
                            'default' => 'NONE',
                            'enum' => [
                                'NONE',
                                'KEYS',
                            ],
                        ]
                    ),
                    new Parameter(
                        name: 'creatorIdEquals',
                        in: 'query',
                        description: 'only return submissions where creatorId equals the specified value',
                        required: false,
                        schema: ['type' => 'string']
                    ),
                ]
            ),
            normalizationContext: [
                'groups' => ['FormalizeSubmission:output', 'FormalizeSubmittedFile:output'],
                'jsonld_embed_context' => true,
            ],
            provider: SubmissionProvider::class,
        ),
        new Post(
            uriTemplate: '/formalize/submissions',
            inputFormats: [
                'multipart' => 'multipart/form-data',
                'jsonld' => 'application/ld+json', // for backwards compatibility
            ],
            controller: PostSubmissionController::class,
            openapi: new Operation(
                tags: ['Formalize'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'form' => [
                                        'type' => 'string',
                                        'example' => '/formalize/forms/<form identifier>',
                                    ],
                                    'dataFeedElement' => [
                                        'type' => 'string',
                                        'example' => '{"firstname": "John", "lastname": "Doe"}',
                                    ],
                                    'submissionState' => [
                                        'type' => 'integer',
                                        'enum' => [
                                            1,
                                            4,
                                        ],
                                        'default' => 4,
                                    ],
                                    'tags' => [
                                        'type' => 'string',
                                        'example' => '["tag 1", "tag 2"]',
                                    ],
                                ],
                                'required' => ['form'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['form', 'dataFeedElement'],
                                'properties' => [
                                    'form' => [
                                        'type' => 'string',
                                        'example' => '/formalize/forms/<form identifier>',
                                    ],
                                    'dataFeedElement' => [
                                        'type' => 'string',
                                        'example' => '{"firstname": "John", "lastname": "Doe"}',
                                    ],
                                ],
                            ],
                        ],
                    ])
                )
            ),
            normalizationContext: [
                'groups' => ['FormalizeSubmission:output', 'FormalizeSubmittedFile:output', 'FormalizeSubmittedFile:file_info_output'],
                'jsonld_embed_context' => true,
            ],
            deserialize: false,
        ),
        new Patch(
            uriTemplate: '/formalize/submissions/{identifier}',
            inputFormats: [
                'multipart' => 'multipart/form-data',
            ],
            controller: PatchSubmissionController::class,
            openapi: new Operation(
                tags: ['Formalize'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'dataFeedElement' => [
                                        'type' => 'string',
                                        'example' => '{"firstname": "John", "lastname": "Doe"}',
                                    ],
                                    'submissionState' => [
                                        'type' => 'integer',
                                        'enum' => [
                                            1,
                                            4,
                                        ],
                                    ],
                                    'tags' => [
                                        'type' => 'string',
                                        'example' => '[ "tag 3"]',
                                    ],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ])
                )
            ),
            normalizationContext: [
                'groups' => ['FormalizeSubmission:output', 'FormalizeSubmittedFile:output', 'FormalizeSubmittedFile:file_info_output'],
                'jsonld_embed_context' => true,
            ],
            deserialize: false,
            provider: SubmissionProvider::class,
        ),
        new Delete(
            uriTemplate: '/formalize/submissions/{identifier}',
            openapi: new Operation(
                tags: ['Formalize'],
            ),
            provider: SubmissionProvider::class,
            processor: SubmissionProcessor::class,
        ),
        new Delete(
            uriTemplate: '/formalize/submissions',
            controller: RemoveAllFormSubmissionsController::class,
            openapi: new Operation(
                tags: ['Formalize'],
                summary: 'Deletes all submissions of a FormalizeForm resource.',
                parameters: [
                    new Parameter(
                        name: 'formIdentifier',
                        in: 'query',
                        description: 'The identifier of the FormalizeForm resource to delete submissions for',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ]
            ),
        ),
    ],
    normalizationContext: [
        'groups' => ['FormalizeSubmission:output'],
        'jsonld_embed_context' => true,
        'preserve_empty_objects' => true,
    ],
    denormalizationContext: [
        'groups' => ['FormalizeSubmission:input'],
    ])]
class Submission
{
    public const SUBMISSION_STATE_DRAFT = 0b00000001;
    // leave empty for potential state between draft and submitted (sneak preview?)
    public const SUBMISSION_STATE_SUBMITTED = 0b00000100;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    #[Groups(['FormalizeSubmission:output'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'data_feed_element', type: 'text', nullable: true)]
    #[Groups(['FormalizeSubmission:output', 'FormalizeSubmission:input'])]
    private ?string $dataFeedElement = null;

    #[ApiProperty(openapiContext: [
        'description' => 'The parent FormalizeForm',
        'example' => '/formalize/forms/7432af11-6f1c-45ee-8aa3-e90b3395e29c'])]
    #[ORM\JoinColumn(name: 'form_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Form::class)]
    #[Groups(['FormalizeSubmission:output', 'FormalizeSubmission:input'])]
    private ?Form $form = null;

    #[ORM\Column(name: 'creator_id', type: 'string', length: 50, nullable: true)]
    #[Groups(['FormalizeSubmission:output'])]
    private ?string $creatorId = null;

    #[ORM\Column(name: 'date_created', type: 'datetime', nullable: false)]
    #[Groups(['FormalizeSubmission:output'])]
    private ?\DateTime $dateCreated = null;

    #[ORM\Column(name: 'last_modified_by_id', type: 'string', length: 50, nullable: true)]
    #[Groups(['FormalizeSubmission:output'])]
    private ?string $lastModifiedById = null;

    #[ORM\Column(name: 'date_last_modified', type: 'datetime', nullable: false)]
    #[Groups(['FormalizeSubmission:output'])]
    private ?\DateTime $dateLastModified = null;

    /**
     * @var string[]
     */
    #[ORM\Column(name: 'tags', type: 'json', nullable: true)]
    #[Groups(['FormalizeSubmission:output:read_all_form_submissions', 'FormalizeSubmission:input'])]
    private ?array $tags = null;

    #[ORM\Column(name: 'submission_state', type: 'smallint', nullable: false, options: ['default' => self::SUBMISSION_STATE_SUBMITTED])]
    #[Groups(['FormalizeSubmission:output', 'FormalizeSubmission:input'])]
    private int $submissionState = self::SUBMISSION_STATE_SUBMITTED;

    #[ORM\OneToMany(targetEntity: SubmittedFile::class, mappedBy: 'submission', cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['FormalizeSubmission:output'])]
    private Collection $submittedFiles;

    #[Groups(['FormalizeSubmission:output'])]
    private array $grantedActions = [];

    /**
     * @var SubmittedFile[]
     */
    private array $submittedFilesToAdd = [];
    /**
     * @var SubmittedFile[]
     */
    private array $submittedFilesToRemove = [];

    public function __construct()
    {
        $this->submittedFiles = new ArrayCollection();
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getDataFeedElement(): ?string
    {
        return $this->dataFeedElement;
    }

    public function setDataFeedElement(?string $dataFeedElement): void
    {
        $this->dataFeedElement = $dataFeedElement;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(Form $form): void
    {
        $this->form = $form;
    }

    public function setDateCreated(\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getCreatorId(): ?string
    {
        return $this->creatorId;
    }

    public function setCreatorId(?string $creatorId): void
    {
        $this->creatorId = $creatorId;
    }

    public function getDateCreated(): ?\DateTime
    {
        return $this->dateCreated;
    }

    public function getLastModifiedById(): ?string
    {
        return $this->lastModifiedById;
    }

    public function setLastModifiedById(?string $lastModifiedById): void
    {
        $this->lastModifiedById = $lastModifiedById;
    }

    public function getDateLastModified(): ?\DateTime
    {
        return $this->dateLastModified;
    }

    public function setDateLastModified(?\DateTime $dateLastModified): void
    {
        $this->dateLastModified = $dateLastModified;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags ?? [];
    }

    /**
     * @param string[]|null $tags
     */
    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }

    public function getSubmissionState(): int
    {
        return $this->submissionState;
    }

    public function setSubmissionState(int $submissionState): void
    {
        $this->submissionState = $submissionState;
    }

    #[Ignore]
    public function isSubmitted(): bool
    {
        return $this->submissionState === self::SUBMISSION_STATE_SUBMITTED;
    }

    #[Ignore]
    public function isDraft(): bool
    {
        return $this->submissionState === self::SUBMISSION_STATE_DRAFT;
    }

    public function getSubmittedFiles(): Collection
    {
        return $this->submittedFiles;
    }

    public function setSubmittedFiles(Collection $submittedFiles): void
    {
        $this->submittedFiles = $submittedFiles;
    }

    public function addSubmittedFile(SubmittedFile $submittedFile): void
    {
        $this->submittedFiles->add($submittedFile);
        $this->submittedFilesToAdd[] = $submittedFile;
    }

    public function removeSubmittedFile(SubmittedFile $submittedFile): bool
    {
        $found = false;
        if ($this->submittedFiles->removeElement($submittedFile)) {
            // WORKAROUND: re-index manually, otherwise, when indices are missing they will be normalized and sent to client
            $submittedFiles = $this->submittedFiles->getValues();
            $this->submittedFiles->clear();
            foreach ($submittedFiles as $file) {
                $this->submittedFiles->add($file);
            }
            $this->submittedFilesToRemove[] = $submittedFile;
            $found = true;
        }

        return $found;
    }

    public function resetSubmittedFileChangesToApply(): void
    {
        $this->submittedFilesToAdd = [];
        $this->submittedFilesToRemove = [];
    }

    public function tryGetSubmittedFile(string $submittedFileIdentifier): ?SubmittedFile
    {
        return $this->submittedFiles->findFirst(
            function (mixed $key, SubmittedFile $submittedFile) use ($submittedFileIdentifier): bool {
                return $submittedFile->getIdentifier() === $submittedFileIdentifier;
            });
    }

    public function getSubmittedFilesToAdd(): array
    {
        return $this->submittedFilesToAdd;
    }

    public function getSubmittedFilesToRemove(): array
    {
        return $this->submittedFilesToRemove;
    }

    public function getGrantedActions(): array
    {
        return $this->grantedActions;
    }

    public function setGrantedActions(array $grantedActions): void
    {
        $this->grantedActions = $grantedActions;
    }

    /**
     * @throws \JsonException
     */
    #[Ignore]
    public function getDataFeedElementDecoded(): ?array
    {
        return $this->dataFeedElement !== null ?
            json_decode($this->dataFeedElement, true, flags: JSON_THROW_ON_ERROR) :
            null;
    }
}
