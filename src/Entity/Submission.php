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
use Dbp\Relay\FormalizeBundle\Rest\RemoveAllFormSubmissionsController;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProcessor;
use Dbp\Relay\FormalizeBundle\Rest\SubmissionProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Serializer;

#[ORM\Table(name: 'formalize_submissions')]
#[ORM\Entity]
#[ApiResource(
    shortName: 'FormalizeSubmission',
    types: ['https://schema.org/DataFeed'],
    operations: [
        new Get(
            uriTemplate: '/formalize/submissions/{identifier}',
            openapiContext: [
                'tags' => ['Formalize'],
            ],
            provider: SubmissionProvider::class
        ),
        new GetCollection(
            uriTemplate: '/formalize/submissions',
            openapiContext: [
                'tags' => ['Formalize'],
                'summary' => 'Retrieves the collection of FormalizeSubmission resources for the specified FormalizeForm resource.',
                'parameters' => [
                    [
                        'name' => 'formIdentifier',
                        'in' => 'query',
                        'description' => 'The identifier of the FormalizeForm resource to get submissions for',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'outputValidation',
                        'in' => 'query',
                        'description' => <<<DESC
                            The output validation filter to apply:
                            * NONE: Don't apply an output validation filter (default)
                            * KEYS: Only return submissions whose keys match those of the form schema
                            DESC,
                        'type' => 'string',
                        'default' => 'NONE',
                        'schema' => [
                            'type' => 'string',
                            'enum' => [
                                'NONE',
                                'KEYS',
                            ],
                        ],
                    ],
                ],
            ],
            provider: SubmissionProvider::class
        ),
        new Post(
            uriTemplate: '/formalize/submissions',
            openapiContext: [
                'tags' => ['Formalize'],
                'requestBody' => [
                    'content' => [
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['form', 'data'],
                                'properties' => [
                                    'form' => [
                                        'type' => 'string',
                                        'example' => '/formalize/forms/7432af11-6f1c-45ee-8aa3-e90b3395e29c',
                                    ],
                                    'data' => [
                                        'type' => 'object',
                                        'example' => [
                                            'firstname' => 'Joni',
                                            'lastname' => 'Doe',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            processor: SubmissionProcessor::class,
        ),
        new Patch(
            uriTemplate: '/formalize/submissions/{identifier}',
            openapiContext: [
                'tags' => ['Formalize'],
                'requestBody' => [
                    'content' => [
                        'application/merge-patch+json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['form', 'data'],
                                'properties' => [
                                    'form' => [
                                        'type' => 'string',
                                    ],
                                    'data' => [
                                        'type' => 'object',
                                        'example' => [
                                            'firstname' => 'John',
                                            'lastname' => 'Doe',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            provider: SubmissionProvider::class,
            processor: SubmissionProcessor::class,
        ),
        new Delete(
            uriTemplate: '/formalize/submissions/{identifier}',
            openapiContext: [
                'tags' => ['Formalize'],
            ],
            provider: SubmissionProvider::class,
            processor: SubmissionProcessor::class,
        ),
        new Delete(
            uriTemplate: '/formalize/submissions',
            controller: RemoveAllFormSubmissionsController::class,
            openapiContext: [
                'tags' => ['Formalize'],
                'summary' => 'Deletes all submissions of a FormalizeForm resource.',
                'parameters' => [
                    [
                        'name' => 'formIdentifier',
                        'in' => 'query',
                        'description' => 'The identifier of the FormalizeForm resource to delete submissions for',
                    ],
                ],
            ],
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
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['FormalizeSubmission:output'])]
    private ?string $identifier = null;

    #[ApiProperty(
        deprecationReason: 'Use JSON object attribute \'data\' instead',
        openapiContext: [
            'deprecated' => true,
        ]
    )]
    #[Groups(['FormalizeSubmission:output', 'FormalizeSubmission:input'])]
    private ?string $dataFeedElement = null;

    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'example' => [
                'firstname' => 'Joni',
                'lastname' => 'Doe',
            ],
        ],
        jsonSchemaContext: [
            'type' => 'object',
        ]
    )]
    #[ORM\Column(name: 'data_feed_element', type: 'json')]
    #[Groups(['FormalizeSubmission:output', 'FormalizeSubmission:input'])]
    #[Context([Serializer::EMPTY_ARRAY_AS_OBJECT => true])]
    private ?array $data = null;

    #[ORM\JoinColumn(name: 'form_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Form::class)]
    #[Groups(['FormalizeSubmission:output', 'FormalizeSubmission:input'])]
    private ?Form $form = null;

    #[ORM\Column(name: 'date_created', type: 'datetime')]
    #[Groups(['FormalizeSubmission:output'])]
    private ?\DateTime $dateCreated = null;

    #[ORM\Column(name: 'creator_id', type: 'string', length: 50, nullable: true)]
    private ?string $creatorId = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @deprecated Use getData() instead
     */
    public function getDataFeedElement(): ?string
    {
        return $this->dataFeedElement;
    }

    /**
     * @deprecated use setData() instead
     */
    public function setDataFeedElement(?string $dataFeedElement): void
    {
        $this->dataFeedElement = $dataFeedElement;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
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

    public function getDateCreated(): ?\DateTime
    {
        return $this->dateCreated;
    }

    public function getCreatorId(): ?string
    {
        return $this->creatorId;
    }

    public function setCreatorId(?string $creatorId): void
    {
        $this->creatorId = $creatorId;
    }

    /**
     * @deprecated Use getData() instead
     */
    #[Ignore]
    public function getDataFeedElementDecoded(): array
    {
        return $this->data;
    }
}
